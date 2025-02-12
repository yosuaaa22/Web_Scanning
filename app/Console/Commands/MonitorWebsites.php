<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Website;
use App\Jobs\WebsiteScanJob;

class MonitorWebsites extends Command
{
    protected $signature = 'monitor:websites';
    protected $description = 'Run scheduled website checks';

    public function handle()
    {
        $websites = Website::where('status', '!=', 'paused')->get();
        
        foreach ($websites as $website) {
            if ($this->shouldCheck($website)) {
                dispatch(new WebsiteMonitorJob($website));
            }
        }
    }

    private function shouldCheck(Website $website)
    {
        return now()->diffInMinutes($website->last_checked) >= $website->check_interval;
    }
}