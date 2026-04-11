<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SignupStatus;
use App\Enums\TenderType;
use App\Models\MembershipOrder;
use App\Models\Payment;
use App\Models\TripSignup;
use App\Models\User;
use App\Services\IdempotencyStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    // ── Record ────────────────────────────────────────────────────────────────

    /**
     * Create a RECORDED payment.
     * Idempotent on idempotency_key.
     */
    public function recordPayment(
        User $user,
        TenderType $tenderType,
        int $amountCents,
        ?string $reference,
        string $idempotencyKey
    ): Payment {
        if ($existing = Payment::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        if ($amountCents <= 0) {
            throw new RuntimeException('Payment amount must be positive.', 422);
        }

        return DB::transaction(function () use ($user, $tenderType, $amountCents, $reference, $idempotencyKey) {
            $payment = Payment::create([
                'user_id'          => $user->id,
                'tender_type'      => $tenderType->value,
                'amount_cents'     => $amountCents,
                'reference_number' => $reference,
                'status'           => PaymentStatus::RECORDED->value,
                'idempotency_key'  => $idempotencyKey,
                'version'          => 1,
            ]);

            AuditService::record('payment.recorded', 'Payment', $payment->id, null, [
                'amount_cents' => $amountCents,
                'tender_type'  => $tenderType->value,
            ]);

            return $payment;
        });
    }

    // ── Confirm ───────────────────────────────────────────────────────────────

    /**
     * Confirm a RECORDED payment. Idempotent on confirmation_event_id.
     */
    public function confirmPayment(Payment $payment, string $confirmationEventId): Payment
    {
        // Idempotency: already confirmed with this event → return as-is
        if ($payment->confirmation_event_id === $confirmationEventId
            && $payment->status === PaymentStatus::CONFIRMED) {
            return $payment;
        }

        // Also check globally: another payment may have this event ID
        $existing = Payment::where('confirmation_event_id', $confirmationEventId)
            ->where('id', '!=', $payment->id)
            ->first();
        if ($existing) {
            throw new RuntimeException('This confirmation event ID is already used by another payment.', 409);
        }

        if ($payment->status !== PaymentStatus::RECORDED) {
            throw new RuntimeException(
                "Cannot confirm a payment in {$payment->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($payment, $confirmationEventId) {
            $before = $payment->toArray();

            $payment->status                = PaymentStatus::CONFIRMED;
            $payment->confirmed_at          = now();
            $payment->confirmation_event_id = $confirmationEventId;
            $payment->saveWithLock();

            AuditService::record('payment.confirmed', 'Payment', $payment->id, $before, [
                'confirmation_event_id' => $confirmationEventId,
            ]);

            // Wire-up: PENDING membership order → PAID.
            // saveWithLock() (not plain save()) so the PENDING→PAID transition
            // honors MembershipOrder's row-version guard. A concurrent void or
            // refund on the same order will surface as StaleRecordException
            // rather than silently clobbering a terminal state.
            $order = MembershipOrder::where('payment_id', $payment->id)->first();
            if ($order && $order->status->value === 'PENDING') {
                $order->status = \App\Enums\OrderStatus::PAID;
                $order->saveWithLock();
                AuditService::record('membership_order.paid', 'MembershipOrder', $order->id, null, [
                    'payment_id' => $payment->id,
                ]);
            }

            return $payment;
        });
    }

    // ── Void ──────────────────────────────────────────────────────────────────

    /**
     * Void a payment. Cascades to cancel linked trip signup or membership order.
     * questions.md 4.5: RECORDED or CONFIRMED → VOIDED.
     *
     * The $idempotencyKey is REQUIRED (FIN audit Issue 4): every mutating
     * state transition on the universal service-layer contract takes a
     * caller-stable key, so a double-click / retry / cascade-loop collapses
     * onto the already-processed row instead of degrading into a 422 on a
     * second attempt. Pass a deterministic per-entity key from Livewire
     * (`payment.void.{paymentId}`) or a UUID from tests.
     */
    public function voidPayment(Payment $payment, string $idempotencyKey): Payment
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'payment.void', $payment->id)) {
            return $payment->fresh();
        }

        if (! in_array($payment->status, [PaymentStatus::RECORDED, PaymentStatus::CONFIRMED], true)) {
            throw new RuntimeException(
                "Cannot void a payment in {$payment->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($payment, $idempotencyKey, $store) {
            $before = $payment->toArray();

            $payment->status = PaymentStatus::VOIDED;
            $payment->saveWithLock();

            // Cascade: cancel linked trip signup
            $signup = TripSignup::where('payment_id', $payment->id)->first();
            if ($signup && ! in_array($signup->status, [SignupStatus::CANCELLED, SignupStatus::EXPIRED], true)) {
                $seatService = app(SeatService::class);
                if ($signup->status === SignupStatus::CONFIRMED) {
                    $seatService->cancelConfirmedSignup($signup);
                } elseif ($signup->status === SignupStatus::HOLD) {
                    $seatService->releaseSeat($signup, \App\Enums\HoldReleaseReason::CANCELLED);
                }
            }

            // Cascade: cancel linked membership order.
            // saveWithLock() so a race with a concurrent refund / top-up /
            // PENDING→PAID transition on the same order surfaces as a
            // StaleRecordException instead of silently overwriting.
            $order = MembershipOrder::where('payment_id', $payment->id)->first();
            if ($order && ! in_array($order->status, [\App\Enums\OrderStatus::REFUNDED, \App\Enums\OrderStatus::VOIDED], true)) {
                $order->status = \App\Enums\OrderStatus::VOIDED;
                $order->saveWithLock();
            }

            AuditService::record('payment.voided', 'Payment', $payment->id, $before, [
                'status' => PaymentStatus::VOIDED->value,
            ]);

            $store->record($idempotencyKey, 'payment.void', 'Payment', $payment->id);

            return $payment;
        });
    }
}
