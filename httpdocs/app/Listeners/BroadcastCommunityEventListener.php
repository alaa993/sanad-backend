<?php

namespace App\Listeners;

use App\Events\CommunityCommentCreated;
use App\Events\CommunityPostCreated;
use App\Events\CommunityPostLiked;
use App\Jobs\BroadcastRealtimeEvent;

class BroadcastCommunityEventListener
{
    public function handle($event): void
    {
        if (!method_exists($event, 'channel')) {
            return;
        }
        $payload = $event->payload;
        if (!isset($payload['communityId'])) {
            $payload['communityId'] = $event->communityId ?? null;
        }
        BroadcastRealtimeEvent::dispatch($event->channel(), $payload);
    }
}
