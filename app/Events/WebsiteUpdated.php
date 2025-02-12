<?php

namespace App\Events;

use App\Models\WebsiteMonitoring;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class WebsiteUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $website;

    /**
     * Buat instance event dengan data website.
     *
     * @param \App\Models\WebsiteMonitoring $website
     */
    public function __construct(WebsiteMonitoring $website)
    {
        $this->website = $website;
    }

    /**
     * Event ini akan di-broadcast ke channel "website.monitoring".
     *
     * @return Channel
     */
    public function broadcastOn()
    {
        return new Channel('website.monitoring');
    }

    /**
     * Data yang akan dikirimkan bersama event.
     */
    public function broadcastWith()
    {
        return [
            'id'             => $this->website->id,
            'name'           => $this->website->name,
            'url'            => $this->website->url,
            'status'         => $this->website->status,
            'last_checked'   => $this->website->last_checked ? $this->website->last_checked->format('Y-m-d H:i:s') : null,
            'vulnerabilities'=> $this->website->vulnerabilities,
        ];
    }
}
