<?php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast {
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $message;
    public function __construct(Message $message){ $this->message = $message->load('sender'); }
    public function broadcastOn(){ return new PrivateChannel('private-chat.'.$this->message->chat_id); }
    public function broadcastWith(){
        return [
            'id'=>$this->message->id,
            'chat_id'=>$this->message->chat_id,
            'sender'=>['id'=>$this->message->sender->id ?? null,'name'=>$this->message->sender->name ?? null],
            'type'=>$this->message->type,
            'body'=>$this->message->body,
            'created_at'=>$this->message->created_at?->toIso8601String(),
        ];
    }
}
