<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $id;
    public $conversation_id;
    public $sender_type;
    public $content;
    public $meta;
    public $created_at;

    public function __construct(Message $msg)
    {
        $this->id = $msg->id;
        $this->conversation_id = $msg->conversation_id;
        $this->sender_type = $msg->sender_type;
        $this->content = $msg->content;
        $this->meta = $msg->meta;
        $this->created_at = $msg->created_at;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->conversation_id);
    }

    public function broadcastAs()
    {
        return 'MessageSent';
    }
}

