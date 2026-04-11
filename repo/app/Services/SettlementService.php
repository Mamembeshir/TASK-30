<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SettlementStatus;
use App\Enums\SignupStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Models\User;
use App\Services\IdempotencyStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SettlementService
{
    // ── Close daily settlement ─────────────────────────────────────────────────

    /**
     * Compute and close the daily settlement for $date.
     * FIN-06: variance > $0.01 → EXCEPTION status + exception record.
     * FIN-07: |variance| <= 1 cent → RECONCILED.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4) — a cron overlap
     * or a manual re-trigger must collapse onto the already-closed
     * settlement row, not degrade into a 409. Deterministic callers
     * (the scheduled command) pass `settlement.close.{date}`; tests pass
     * a UUID per invocation.
     */
    public function closeDailySettlement(Carbon|string $date, string $idempotencyKey): Settlement
    {
        $date  = Carbon::parse($date)->toDateString();
        $store = new IdempotencyStore();

        // Resolve the facility-local day boundaries once. Payments and refunds
        // store their timestamps as UTC (Laravel's default), but business day
        // reconciliation must line up with the *facility* calendar day.
        // `whereDate('confirmed_at', $date)` would compare the UTC date part
        // of the column and silently exclude the first few hours of a
        // non-UTC facility day — so we convert the facility day bounds into
        // UTC instants and range-query on them instead.
        $facilityTz = config('app.facility_timezone', config('app.timezone', 'UTC'));
        $startUtc   = Carbon::parse($date, $facilityTz)->startOfDay()->utc();
        $endUtc     = Carbon::parse($date, $facilityTz)->endOfDay()->utc();

        return DB::transaction(function () use ($date, $idempotencyKey, $store, $startUtc, $endUtc) {
            // Find or create the settlement for this date
            $settlement = Settlement::firstOrCreate(
                ['settlement_date' => $date],
                [
                    'status'                => SettlementStatus::OPEN->value,
                    'total_payments_cents'  => 0,
                    'total_refunds_cents'   => 0,
                    'net_amount_cents'      => 0,
                    'expected_amount_cents' => 0,
                    'variance_cents'        => 0,
                    'version'               => 1,
                ]
            );

            if ($store->alreadyProcessed($idempotencyKey, 'settlement.close', $settlement->id)) {
                return $settlement->fresh();
            }

            if ($settlement->status === SettlementStatus::RECONCILED) {
                throw new RuntimeException('Settlement is already reconciled.', 409);
            }

            // Sum CONFIRMED payments whose confirmed_at falls inside the
            // facility day (UTC range query — see comment above).
            $totalPayments = (int) Payment::where('status', PaymentStatus::CONFIRMED->value)
                ->whereBetween('confirmed_at', [$startUtc, $endUtc])
                ->sum('amount_cents');

            // Sum PROCESSED refunds whose processed_at falls inside the
            // same facility day range.
            $totalRefunds = (int) Refund::where('status', RefundStatus::PROCESSED->value)
                ->whereBetween('processed_at', [$startUtc, $endUtc])
                ->sum('amount_cents');

            $net      = $totalPayments - $totalRefunds;

            // Deterministic expected baseline derived from closed obligations
            // (FIN audit Issue 2). See computeExpectedForDay() for details —
            // every payment confirmed today that is linked to a valid signup
            // or order contributes its *obligation* price, not the payment
            // amount. Divergence between the two sides is what the variance
            // check is here to surface.
            $expected = $this->computeExpectedForDay($startUtc, $endUtc);
            $variance = $net - $expected;

            $settlement->total_payments_cents  = $totalPayments;
            $settlement->total_refunds_cents   = $totalRefunds;
            $settlement->net_amount_cents      = $net;
            $settlement->expected_amount_cents = $expected;
            $settlement->variance_cents        = $variance;
            $settlement->closed_at             = now();

            // Link all included payments
            Payment::where('status', PaymentStatus::CONFIRMED->value)
                ->whereBetween('confirmed_at', [$startUtc, $endUtc])
                ->whereNull('settlement_id')
                ->update(['settlement_id' => $settlement->id]);

            // Determine status: FIN-07 vs FIN-06
            if (abs($variance) <= 1) {
                $settlement->status         = SettlementStatus::RECONCILED;
                $settlement->reconciled_at  = now();
            } else {
                $settlement->status = SettlementStatus::EXCEPTION;

                SettlementException::create([
                    'settlement_id'   => $settlement->id,
                    'exception_type'  => ExceptionType::VARIANCE->value,
                    'description'     => sprintf(
                        'Settlement variance of %s detected. Expected %s, got %s.',
                        formatCurrency(abs($variance)),
                        formatCurrency($expected),
                        formatCurrency($net)
                    ),
                    'amount_cents'    => $variance,
                    'status'          => ExceptionStatus::OPEN->value,
                    'version'         => 1,
                ]);
            }

            // Generate statement CSV
            $filePath = $this->generateStatementFile($settlement, $date);
            $settlement->statement_file_path = $filePath;

            $settlement->saveWithLock();

            AuditService::record('settlement.closed', 'Settlement', $settlement->id, null, [
                'date'                => $date,
                'total_payments'      => $totalPayments,
                'total_refunds'       => $totalRefunds,
                'variance_cents'      => $variance,
                'status'              => $settlement->status->value,
            ]);

            $store->record($idempotencyKey, 'settlement.close', 'Settlement', $settlement->id);

            return $settlement->fresh();
        });
    }

    // ── Resolve exception ─────────────────────────────────────────────────────

    /**
     * Resolve or write-off an OPEN exception.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function resolveException(
        SettlementException $exception,
        ExceptionStatus $resolution,
        string $notes,
        User $resolver,
        string $idempotencyKey,
    ): SettlementException {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'settlement_exception.resolve', $exception->id)) {
            return $exception->fresh();
        }

        if ($exception->status !== ExceptionStatus::OPEN) {
            throw new RuntimeException('Exception is already resolved.', 409);
        }

        if (! in_array($resolution, [ExceptionStatus::RESOLVED, ExceptionStatus::WRITTEN_OFF], true)) {
            throw new RuntimeException('Resolution must be RESOLVED or WRITTEN_OFF.', 422);
        }

        return DB::transaction(function () use ($exception, $resolution, $notes, $resolver, $idempotencyKey, $store) {
            $exception->status          = $resolution;
            $exception->resolved_by     = $resolver->id;
            $exception->resolution_note = $notes;
            $exception->save();

            AuditService::record('settlement_exception.resolved', 'SettlementException', $exception->id, null, [
                'resolution' => $resolution->value,
                'notes'      => $notes,
            ]);

            $store->record($idempotencyKey, 'settlement_exception.resolve', 'SettlementException', $exception->id);

            return $exception;
        });
    }

    // ── Re-reconcile ──────────────────────────────────────────────────────────

    /**
     * If all exceptions on a settlement are resolved, transition it to RECONCILED.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function reReconcile(Settlement $settlement, string $idempotencyKey): Settlement
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'settlement.reconcile', $settlement->id)) {
            return $settlement->fresh();
        }

        if ($settlement->status !== SettlementStatus::EXCEPTION) {
            throw new RuntimeException('Only EXCEPTION settlements can be re-reconciled.', 422);
        }

        $openExceptions = $settlement->exceptions()
            ->where('status', ExceptionStatus::OPEN->value)
            ->count();

        if ($openExceptions > 0) {
            throw new RuntimeException(
                "Cannot reconcile: {$openExceptions} open exception(s) remain.",
                422
            );
        }

        return DB::transaction(function () use ($settlement, $idempotencyKey, $store) {
            $settlement->status        = SettlementStatus::RECONCILED;
            $settlement->reconciled_at = now();
            $settlement->saveWithLock();

            AuditService::record('settlement.reconciled', 'Settlement', $settlement->id, null, [
                'status' => SettlementStatus::RECONCILED->value,
            ]);

            $store->record($idempotencyKey, 'settlement.reconcile', 'Settlement', $settlement->id);

            return $settlement->fresh();
        });
    }

    // ── Export statement ──────────────────────────────────────────────────────

    /**
     * Return the storage path of the settlement's statement file, generating
     * it if needed.
     *
     * Every call records an audit entry (audit Issue 5). The entry captures
     * the acting user via AuditService → auth()->id(), the settlement id,
     * the file path, and whether the file had to be (re)generated. This
     * closes the end-to-end export traceability gap called out in the
     * prompt.
     */
    public function exportStatement(Settlement $settlement): string
    {
        if ($settlement->statement_file_path && Storage::disk('local')->exists($settlement->statement_file_path)) {
            $path       = $settlement->statement_file_path;
            $regenerated = false;
        } else {
            $path       = $this->generateStatementFile($settlement, $settlement->settlement_date->toDateString());
            $regenerated = true;
        }

        AuditService::record(
            action:     'settlement.statement_exported',
            entityType: 'Settlement',
            entityId:   $settlement->id,
            before:     null,
            after:      [
                'settlement_date' => $settlement->settlement_date->toDateString(),
                'file_path'       => $path,
                'regenerated'     => $regenerated,
            ],
        );

        return $path;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Compute the deterministic expected-collection baseline for a day.
     *
     * The variance check compares two **independent** sources:
     *  - `net` (payments minus refunds) — the cash-side view, derived from
     *    whatever finance staff keyed into the Payment ledger.
     *  - `expected` (this method) — the obligation-side view, derived from
     *    the domain records those payments were supposed to satisfy:
     *       • A CONFIRMED trip signup's obligation is the *trip's* price
     *         (`trips.price_cents`), not what was keyed on the payment.
     *       • A PAID / REFUNDED / PARTIALLY_REFUNDED membership order's
     *         obligation is `membership_orders.amount_cents`.
     *    Both are scoped to the facility day by joining through the linked
     *    payment's `confirmed_at` (so the two sides share the same time
     *    window even though obligations have no separate timestamp).
     *
     * PROCESSED refunds are then subtracted to mirror the way `net` already
     * nets refunds out of the cash side — so perfectly-coherent data gives
     * `variance = 0`. Real variance can only arise when a payment's keyed
     * amount drifts from its linked obligation price, a payment is missing
     * its obligation linkage, or a refund is not mirrored by an obligation
     * reversal — which is exactly the class of error FIN-06/07 are here to
     * flag as an EXCEPTION.
     *
     * `expected_amount_cents` is written back to the settlement row, giving
     * the Finance dashboard a meaningful Expected / Actual / Variance trio
     * and replacing the previous behaviour where `expected` was whatever a
     * test or admin had pre-stamped on the row (audit Issue 2: "not
     * computed by the system").
     */
    private function computeExpectedForDay(Carbon $startUtc, Carbon $endUtc): int
    {
        // Obligation side: CONFIRMED trip signups whose payment was
        // confirmed inside the window. Price is sourced from the trip,
        // not the payment, so a keyed-amount mismatch surfaces as variance.
        $tripObligations = (int) DB::table('trip_signups')
            ->join('trips', 'trips.id', '=', 'trip_signups.trip_id')
            ->join('payments', 'payments.id', '=', 'trip_signups.payment_id')
            ->where('trip_signups.status', SignupStatus::CONFIRMED->value)
            ->where('payments.status', PaymentStatus::CONFIRMED->value)
            ->whereBetween('payments.confirmed_at', [$startUtc, $endUtc])
            ->sum('trips.price_cents');

        // Obligation side: membership orders that landed in a collected
        // state (PAID or its refund-derived siblings) via a payment
        // confirmed inside the window. REFUNDED / PARTIALLY_REFUNDED orders
        // stay in the expected base — the refund itself is netted below so
        // we don't double-count the reversal.
        $orderObligations = (int) DB::table('membership_orders')
            ->join('payments', 'payments.id', '=', 'membership_orders.payment_id')
            ->whereIn('membership_orders.status', [
                OrderStatus::PAID->value,
                OrderStatus::REFUNDED->value,
                OrderStatus::PARTIALLY_REFUNDED->value,
            ])
            ->where('payments.status', PaymentStatus::CONFIRMED->value)
            ->whereBetween('payments.confirmed_at', [$startUtc, $endUtc])
            ->sum('membership_orders.amount_cents');

        // Refund reversals: obligation-side mirror of the cash-side refund
        // total already subtracted from `net`. Kept symmetric so a refund
        // that *does* match its original obligation contributes 0 to the
        // variance.
        $refundReversals = (int) Refund::where('status', RefundStatus::PROCESSED->value)
            ->whereBetween('processed_at', [$startUtc, $endUtc])
            ->sum('amount_cents');

        return $tripObligations + $orderObligations - $refundReversals;
    }

    private function generateStatementFile(Settlement $settlement, string $date): string
    {
        $dir  = 'statements';
        $file = "{$dir}/settlement-{$date}.csv";

        Storage::disk('local')->makeDirectory($dir);

        $payments = Payment::where('settlement_id', $settlement->id)
            ->with('user')
            ->get();

        $lines = ["id,user_email,tender_type,amount_cents,confirmed_at,reference_number"];
        foreach ($payments as $p) {
            $lines[] = implode(',', [
                $p->id,
                $p->user?->email ?? '',
                $p->tender_type->value,
                $p->amount_cents,
                $p->confirmed_at?->toDateTimeString() ?? '',
                $p->reference_number ?? '',
            ]);
        }

        $lines[] = "";
        $lines[] = "Summary";
        $lines[] = "total_payments_cents,{$settlement->total_payments_cents}";
        $lines[] = "total_refunds_cents,{$settlement->total_refunds_cents}";
        $lines[] = "net_amount_cents,{$settlement->net_amount_cents}";
        $lines[] = "variance_cents,{$settlement->variance_cents}";
        $lines[] = "status,{$settlement->status->value}";

        Storage::disk('local')->put($file, implode("\n", $lines));

        return $file;
    }
}
