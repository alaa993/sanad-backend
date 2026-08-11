<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private-chat.{chatId}', function ($user, $chatId) {
    return \App\Models\ChatParticipant::where('chat_id', $chatId)
        ->where('user_id', $user->id)->exists();
});
