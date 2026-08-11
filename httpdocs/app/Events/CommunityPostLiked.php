<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunityPostLiked
{
    use Dispatchable, SerializesModels;

    public int $communityId;
    public array $payload;

    public function __construct(int $communityId, array $payload)
    {
        $this->communityId = $communityId;
        $this->payload = $payload;
    }

    public function channel(): string
    {
        return 'community:like';
    }
}
