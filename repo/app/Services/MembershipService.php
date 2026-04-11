<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MembershipService
{
    // ── Purchase ───────────────────────────────────────────────────────────────

    /**
     * Create a new PENDING membership order.
     * questions.md 3.2: only allowed when the user has NO currently active membership.
     */
    public function purchase(User $user, MembershipPlan $plan, string $idempotencyKey): MembershipOrder
    {
        // Idempotency: return existing order for this key
        if ($existing = MembershipOrder::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        if ($user->activeMembership() !== null) {
            throw new RuntimeException(
                'You already have an active membership. Renew or upgrade instead.',
                422
            );
        }

        if (! $plan->is_active) {
            throw new RuntimeException('This membership plan is no longer available.', 422);
        }

        return DB::transaction(function () use ($user, $plan, $idempotencyKey) {
            $now      = now();
            $expiresAt = $now->copy()->addMonths($plan->duration_months);

            $order = MembershipOrder::create([
                'user_id'               => $user->id,
                'plan_id'               => $plan->id,
                'order_type'            => OrderType::PURCHASE->value,
                'amount_cents'          => $plan->price_cents,
                'status'                => OrderStatus::PENDING->value,
                'starts_at'             => $now,
                'expires_at'            => $expiresAt,
                'top_up_eligible_until' => $now->copy()->addDays(30),
                'idempotency_key'       => $idempotencyKey,
            ]);

            AuditService::record('membership.purchased', 'MembershipOrder', $order->id, null, [
                'plan_id' => $plan->id,
                'tier'    => $plan->tier->value,
                'amount'  => $plan->price_cents,
            ]);

            return $order;
        });
    }

    // ── Renew ──────────────────────────────────────────────────────────────────

    /**
     * MEM-02: Extend from current expires_at (or now if already expired).
     */
    public function renew(User $user, MembershipPlan $plan, string $idempotencyKey): MembershipOrder
    {
        if ($existing = MembershipOrder::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        if (! $plan->is_active) {
            throw new RuntimeException('This membership plan is no longer available.', 422);
        }

        return DB::transaction(function () use ($user, $plan, $idempotencyKey) {
            $current   = $user->activeMembership();
            $baseDate  = ($current && $current->expires_at->isFuture())
                ? $current->expires_at
                : now();

            $expiresAt = $baseDate->copy()->addMonths($plan->duration_months);

            $order = MembershipOrder::create([
                'user_id'               => $user->id,
                'plan_id'               => $plan->id,
                'order_type'            => OrderType::RENEWAL->value,
                'amount_cents'          => $plan->price_cents,
                'status'                => OrderStatus::PENDING->value,
                'starts_at'             => now(),
                'expires_at'            => $expiresAt,
                'top_up_eligible_until' => now()->addDays(30),
                'idempotency_key'       => $idempotencyKey,
            ]);

            AuditService::record('membership.renewed', 'MembershipOrder', $order->id, null, [
                'plan_id'    => $plan->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return $order;
        });
    }

    // ── Top-up ─────────────────────────────────────────────────────────────────

    /**
     * questions.md 3.1 / MEM-03 / MEM-04:
     * - Only within 30 days of original purchase.
     * - Only to a higher tier (upgrade, not downgrade).
     * - New order amount = new plan price − current plan price.
     * - New order expires_at = current order expires_at (keeps original duration).
     */
    public function topUp(User $user, MembershipPlan $newPlan, string $idempotencyKey): MembershipOrder
    {
        if ($existing = MembershipOrder::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        $current = $user->activeMembership();

        if ($current === null) {
            throw new RuntimeException('You need an active membership to perform an upgrade.', 422);
        }

        if (! $current->isTopUpEligible()) {
            throw new RuntimeException(
                'The 30-day upgrade window for your current plan has expired.',
                422
            );
        }

        $currentTier = $current->plan->tier;
        $newTier     = $newPlan->tier;

        if (! $newTier->isHigherThan($currentTier)) {
            throw new RuntimeException(
                "Cannot downgrade from {$currentTier->label()} to {$newTier->label()}. Only upgrades are allowed.",
                422
            );
        }

        if (! $newPlan->is_active) {
            throw new RuntimeException('This membership plan is no longer available.', 422);
        }

        return DB::transaction(function () use ($user, $current, $newPlan, $idempotencyKey) {
            $priceDiff = max(0, $newPlan->price_cents - $current->plan->price_cents);

            $order = MembershipOrder::create([
                'user_id'               => $user->id,
                'plan_id'               => $newPlan->id,
                'order_type'            => OrderType::TOP_UP->value,
                'amount_cents'          => $priceDiff,
                'previous_order_id'     => $current->id,
                'status'                => OrderStatus::PENDING->value,
                'starts_at'             => now(),
                'expires_at'            => $current->expires_at, // keeps original duration
                'top_up_eligible_until' => null,                  // no further top-ups on top-ups
                'idempotency_key'       => $idempotencyKey,
            ]);

            AuditService::record('membership.topped_up', 'MembershipOrder', $order->id, null, [
                'from_plan'  => $current->plan_id,
                'to_plan'    => $newPlan->id,
                'amount'     => $priceDiff,
                'expires_at' => $current->expires_at->toIso8601String(),
            ]);

            return $order;
        });
    }

    // ── Refund ─────────────────────────────────────────────────────────────────

    /**
     * questions.md 3.3: Refunds are per-order, not per-lifecycle.
     * The order must be PAID and must have a linked payment.
     */
    public function requestRefund(
        MembershipOrder $order,
        RefundType      $type,
        string          $reason,
        ?int            $amountCents = null,
        string          $idempotencyKey = '',
        ?string         $actorId = null,
    ): Refund {
        if ($actorId !== null && (string) $order->user_id !== $actorId) {
            throw new RuntimeException('You are not authorised to refund this order.', 403);
        }

        if ($existing = Refund::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        if ($order->status !== OrderStatus::PAID) {
            throw new RuntimeException('Only PAID orders can be refunded.', 422);
        }

        if (! $order->payment_id) {
            throw new RuntimeException('This order has no linked payment record.', 422);
        }

        if ($type === RefundType::PARTIAL) {
            if ($amountCents === null || $amountCents <= 0 || $amountCents >= $order->amount_cents) {
                throw new RuntimeException(
                    'Partial refund amount must be greater than 0 and less than the order total.',
                    422
                );
            }
        }

        if (strlen(trim($reason)) < 10) {
            throw new RuntimeException('Refund reason must be at least 10 characters.', 422);
        }

        return DB::transaction(function () use ($order, $type, $reason, $amountCents, $idempotencyKey) {
            $refundAmount = $type === RefundType::FULL
                ? $order->amount_cents
                : $amountCents;

            $refund = Refund::create([
                'payment_id'      => $order->payment_id,
                'amount_cents'    => $refundAmount,
                'refund_type'     => $type->value,
                'reason'          => $reason,
                'status'          => RefundStatus::PENDING->value,
                'idempotency_key' => $idempotencyKey,
            ]);

            AuditService::record('refund.requested', 'Refund', $refund->id, null, [
                'order_id' => $order->id,
                'type'     => $type->value,
                'amount'   => $refundAmount,
            ]);

            return $refund;
        });
    }

    /**
     * Finance approves a pending refund. PENDING → APPROVED.
     */
    public function approveRefund(Refund $refund, User $approver): Refund
    {
        if ($refund->status !== RefundStatus::PENDING) {
            throw new RuntimeException('Only PENDING refunds can be approved.', 422);
        }

        return DB::transaction(function () use ($refund, $approver) {
            $before         = ['status' => $refund->status->value];
            $refund->status      = RefundStatus::APPROVED;
            $refund->approved_by = $approver->id;
            $refund->saveWithLock();

            AuditService::record('refund.approved', 'Refund', $refund->id, $before, [
                'status'      => RefundStatus::APPROVED->value,
                'approved_by' => $approver->id,
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Process an approved refund. APPROVED → PROCESSED.
     * Full refund  → order REFUNDED, expires_at = now (terminates membership).
     * Partial      → order PARTIALLY_REFUNDED, membership stays active.
     */
    public function processRefund(Refund $refund): Refund
    {
        if ($refund->status !== RefundStatus::APPROVED) {
            throw new RuntimeException('Only APPROVED refunds can be processed.', 422);
        }

        return DB::transaction(function () use ($refund) {
            $before              = ['status' => $refund->status->value];
            $refund->status      = RefundStatus::PROCESSED;
            $refund->processed_at = now();
            $refund->saveWithLock();

            // Find the order linked via payment
            $order = MembershipOrder::where('payment_id', $refund->payment_id)->first();

            if ($order) {
                $orderBefore = ['status' => $order->status->value];

                if ($refund->refund_type === RefundType::FULL) {
                    // questions.md 3.3: full refund terminates the membership immediately
                    $order->status     = OrderStatus::REFUNDED;
                    $order->expires_at = now();
                } else {
                    // Partial: membership stays active
                    $order->status = OrderStatus::PARTIALLY_REFUNDED;
                }

                $order->saveWithLock();

                AuditService::record('membership_order.refund_applied', 'MembershipOrder', $order->id, $orderBefore, [
                    'status'      => $order->status->value,
                    'refund_type' => $refund->refund_type->value,
                ]);
            }

            AuditService::record('refund.processed', 'Refund', $refund->id, $before, [
                'status'       => RefundStatus::PROCESSED->value,
                'processed_at' => now()->toIso8601String(),
            ]);

            return $refund->fresh();
        });
    }
}
