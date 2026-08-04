<?php

namespace App\Console\Commands;

use App\Support\AcceptWindowSla;
use Illuminate\Console\Command;

class WarnKitchenAcceptWindows extends Command
{
    protected $signature = 'kitchen:warn-accept-windows';

    protected $description = 'Create in-app alerts for kitchens when open pool groups are near accept-window close';

    public function handle(): int
    {
        $result = AcceptWindowSla::warnEligible();

        $this->info(sprintf(
            'Checked %d closing group(s); created %d alert(s).',
            $result['groups_checked'],
            $result['alerts_created']
        ));

        return self::SUCCESS;
    }
}
