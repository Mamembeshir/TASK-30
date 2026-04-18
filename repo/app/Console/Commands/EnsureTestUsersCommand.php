<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotently force the five seeded demo accounts to have the known test
 * password ("Seed1234!@") and zero AUTH-02 lockout counters.
 *
 * Runs on every container boot (entrypoint.sh) and at the start of the E2E
 * suite (run_tests.sh) so the Playwright login tests always work regardless
 * of whatever state the database was left in by a prior run or experiment.
 *
 * Only resets the five well-known E2E accounts — real user data is not
 * touched.
 */
class EnsureTestUsersCommand extends Command
{
    protected $signature   = 'medvoyage:ensure-test-users';
    protected $description = 'Force-reset password + lockout counters on the demo E2E accounts (idempotent).';

    private const TEST_EMAILS = [
        'admin@medvoyage.test',
        'reviewer@medvoyage.test',
        'finance@medvoyage.test',
        'doctor@medvoyage.test',
        'member@medvoyage.test',
    ];

    private const TEST_PASSWORD = 'Seed1234!@';

    public function handle(): int
    {
        foreach (self::TEST_EMAILS as $email) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->warn("  MISSING {$email}");
                continue;
            }
            $user->forceFill([
                'password'           => Hash::make(self::TEST_PASSWORD),
                'failed_login_count' => 0,
                'locked_until'       => null,
            ])->save();
            $this->info("  OK {$email}");
        }

        return self::SUCCESS;
    }
}
