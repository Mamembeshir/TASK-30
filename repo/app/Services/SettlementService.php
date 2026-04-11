<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SettlementStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Models\User;
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
     */
    public function closeDailySettlement(Carbon|string $date): Settlement
    {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($date) {
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

            if ($settlement->status === SettlementStatus::RECONCILED) {
                throw new RuntimeException('Settlement is already reconciled.', 409);
            }

            // Sum CONFIRMED payments for this date
            $totalPayments = (int) Payment::where('status', PaymentStatus::CONFIRMED->value)
                ->whereDate('confirmed_at', $date)
                ->sum('amount_cents');

            // Sum PROCESSED refunds for this date
            $totalRefunds = (int) Refund::where('status', RefundStatus::PROCESSED->value)
                ->whereDate('processed_at', $date)
                ->sum('amount_cents');

            $net      = $totalPayments - $totalRefunds;
            $expected = $settlement->expected_amount_cents;
            $variance = $net - $expected;

            $settlement->total_payments_cents = $totalPayments;
            $settlement->total_refunds_cents  = $totalRefunds;
            $settlement->net_amount_cents     = $net;
            $settlement->variance_cents       = $variance;
            $settlement->closed_at            = now();

            // Link all included payments
            Payment::where('status', PaymentStatus::CONFIRMED->value)
                ->whereDate('confirmed_at', $date)
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

            return $settlement->fresh();
        });
    }

    // ── Resolve exception ─────────────────────────────────────────────────────

    /**
     * Resolve or write-off an OPEN exception.
     */
    public function resolveException(
        SettlementException $exception,
        ExceptionStatus $resolution,
        string $notes,
        User $resolver
    ): SettlementException {
        if ($exception->status !== ExceptionStatus::OPEN) {
            throw new RuntimeException('Exception is already resolved.', 409);
        }

        if (! in_array($resolution, [ExceptionStatus::RESOLVED, ExceptionStatus::WRITTEN_OFF], true)) {
            throw new RuntimeException('Resolution must be RESOLVED or WRITTEN_OFF.', 422);
        }

        return DB::transaction(function () use ($exception, $resolution, $notes, $resolver) {
            $exception->status          = $resolution;
            $exception->resolved_by     = $resolver->id;
            $exception->resolution_note = $notes;
            $exception->save();

            AuditService::record('settlement_exception.resolved', 'SettlementException', $exception->id, null, [
                'resolution' => $resolution->value,
                'notes'      => $notes,
            ]);

            return $exception;
        });
    }

    // ── Re-reconcile ──────────────────────────────────────────────────────────

    /**
     * If all exceptions on a settlement are resolved, transition it to RECONCILED.
     */
    public function reReconcile(Settlement $settlement): Settlement
    {
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

        return DB::transaction(function () use ($settlement) {
            $settlement->status        = SettlementStatus::RECONCILED;
            $settlement->reconciled_at = now();
            $settlement->saveWithLock();

            AuditService::record('settlement.reconciled', 'Settlement', $settlement->id, null, [
                'status' => SettlementStatus::RECONCILED->value,
            ]);

            return $settlement->fresh();
        });
    }

    // ── Export statement ──────────────────────────────────────────────────────

    /**
     * Return the storage path of the settlement's statement file, generating it if needed.
     */
    public function exportStatement(Settlement $settlement): string
    {
        if ($settlement->statement_file_path && Storage::disk('local')->exists($settlement->statement_file_path)) {
            return $settlement->statement_file_path;
        }

        return $this->generateStatementFile($settlement, $settlement->settlement_date->toDateString());
    }

    // ── Private ───────────────────────────────────────────────────────────────

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
