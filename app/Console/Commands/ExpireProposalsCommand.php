<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Matching\Services\ProposalExpirationService;

class ExpireProposalsCommand extends Command
{
    protected $signature = 'proposals:expire';
    protected $description = 'Expire overdue proposals and notify clients';

    public function handle(ProposalExpirationService $service): int
    {
        $count = $service->expireOverdue();

        if ($count > 0) {
            $this->info("Expired {$count} overdue proposal(s).");
        } else {
            $this->info('No overdue proposals found.');
        }

        return self::SUCCESS;
    }
}
