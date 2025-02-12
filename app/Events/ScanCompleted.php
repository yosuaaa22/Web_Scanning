<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScanCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $scanData;
    public $performanceData;

    public function __construct($scanData, $performanceData)
    {
        $this->scanData = $scanData;
        $this->performanceData = $performanceData;
    }

    public function broadcastOn()
    {
        return new Channel('scan-updates');
    }
}