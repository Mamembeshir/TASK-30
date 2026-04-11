<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Audit Issue 4: walk the audit_logs chain and report the first row whose
 * stored row_hash does not match the canonical SHA-256 recomputed from the
 * row's current field values, or whose previous_hash does not match the
 * preceding row's row_hash.
 *
 * A clean run proves every byte of every column in the chain is unchanged
 * since it was written, assuming the hash of any one row is pinned somewhere
 * out-of-band (e.g., logged off-box, written to a WORM store).
 */
class VerifyAuditChain extends Command
{
    protected $signature   = 'medvoyage:verify-audit-chain';
    protected $description = 'Verify the audit_logs hash chain has not been tampered with.';

    public function handle(): int
    {
        $previousHash = null;
        $checked      = 0;
        $firstBad     = null;

        // Stream in insertion order so previous_hash linkage is meaningful.
        AuditLog::query()
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (AuditLog $entry) use (&$previousHash, &$checked, &$firstBad) {
                if ($firstBad !== null) {
                    return;
                }

                // The row's stored previous_hash must match the previous
                // row's row_hash (null for the very first row).
                if ($entry->previous_hash !== $previousHash) {
                    $firstBad = [
                        'id'     => $entry->id,
                        'reason' => 'previous_hash does not match preceding row_hash',
                        'expected' => $previousHash,
                        'actual'   => $entry->previous_hash,
                    ];
                    return;
                }

                // The row's stored row_hash must match recomputing from its
                // current column values. A mismatch means at least one field
                // was mutated after insert.
                $recomputed = AuditLog::computeHash($entry);
                if (! hash_equals((string) $entry->row_hash, $recomputed)) {
                    $firstBad = [
                        'id'       => $entry->id,
                        'reason'   => 'row_hash does not match recomputed value (field tampered)',
                        'expected' => $recomputed,
                        'actual'   => $entry->row_hash,
                    ];
                    return;
                }

                $previousHash = $entry->row_hash;
                $checked++;
            });

        if ($firstBad !== null) {
            $this->error('Audit chain verification FAILED.');
            $this->line("  Broken at row: {$firstBad['id']}");
            $this->line("  Reason:        {$firstBad['reason']}");
            $this->line("  Expected:      {$firstBad['expected']}");
            $this->line("  Actual:        {$firstBad['actual']}");
            $this->line("  Rows checked before break: {$checked}");
            return self::FAILURE;
        }

        $this->info("Audit chain OK. {$checked} row(s) verified.");
        return self::SUCCESS;
    }
}
