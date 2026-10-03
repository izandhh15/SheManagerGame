<?php

namespace App\Jobs;

use App\Models\WaitlistEntry;
use App\Services\BetaInviteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBulkWaitlistInvites implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /**
     * Anti-double-submit: only one bulk-invite run may be queued/running
     * at a time. The lock is released when the job finishes, so a later
     * legitimate batch is not blocked.
     */
    public int $uniqueFor = 900;

    public function uniqueId(): string
    {
        return 'bulk-waitlist-invites';
    }

    public function __construct(
        private readonly int $count,
    ) {
        $this->onQueue('mail');
    }

    public function handle(BetaInviteService $inviteService): void
    {
        $entries = WaitlistEntry::whereDoesntHave('inviteCode')
            ->earlyAdopter()
            ->inRandomOrder()
            ->limit($this->count)
            ->get();

        $sent = 0;

        foreach ($entries as $entry) {
            $inviteService->invite($entry);
            $sent++;

            if ($sent < $entries->count()) {
                sleep(1);
            }
        }
    }
}
