<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Jobs\WebsiteScanJob;
use Illuminate\Console\Command;

class CheckWebsiteScan extends Command
{
    protected $signature = 'scan:check';
    protected $description = 'Check websites for scheduled scanning';

    public function handle()
    {
        $websites = Website::all();

        foreach ($websites as $website) {
            $lastChecked = $website->last_checked ?? now()->subMinutes($website->check_interval + 1);
            $nextCheck = $lastChecked->addMinutes($website->check_interval);

            if (now()->greaterThanOrEqualTo($nextCheck)) {
                dispatch(new WebsiteScanJob($website->id));
                $this->info("Dispatching scan for: {$website->url}");
            }
        }
    }
}