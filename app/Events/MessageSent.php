<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
// ADD THESE - We need them for manual loading
use App\Models\User;
use App\Models\BusinessDetail;


class MessageSent implements ShouldBroadcast
{
  use InteractsWithSockets, SerializesModels;

  public Message $message;
  public ?int $businessId = null;

  public function __construct(Message $message)
  {
    Log::debug('MessageSent Event: Constructor started', ['message_id' => $message->message_id]);

    // --- 1. Find the Business ID for the channel ---
    if ($message->sender_role == 'business') {
      $this->businessId = $message->sender_id;
    } elseif ($message->receiver_role == 'business') {
      $this->businessId = $message->receiver_id; // Fixed typo here
    }

    if ($this->businessId) {
      Log::debug('MessageSent Event: Found businessId for channel', ['id' => $this->businessId]);
    } else {
      Log::warning('MessageSent Event: COULD NOT find businessId in message.', ['message_id' => $message->message_id]);
    }

    // --- 2. Prepare the Message for Broadcast ---

    // $message->load('sender'); // <-- REMOVED

    // --- MANUALLY LOAD SENDER ---
    if ($message->sender_role == 'business') {
      $sender = BusinessDetail::find($message->sender_id);
    } else { // 'customer'
      $sender = User::find($message->sender_id);
    }
    $message->sender = $sender; // Manually attach the sender model
    // --- END MANUAL LOAD ---

    $this->message = $message;

    Log::debug('MessageSent Event: Constructor finished', [
      'broadcast_message_text' => $this->message->message_text,
      'sender_loaded' => isset($message->sender)
    ]);
  }

  public function broadcastOn(): ?Channel
  {
    if ($this->businessId !== null) {
      Log::debug('Broadcasting on channel:', ['channel' => 'chat.business.' . $this->businessId]);
      return new Channel('chat.business.' . $this->businessId);
    }
    Log::warning('MessageSent Event: businessId is null. Not broadcasting.');
    return null;
  }

  public function broadcastWith(): array
  {
    Log::debug('Broadcasting with payload:', ['message' => $this->message->toArray()]);
    return ['message' => $this->message->toArray()]; // Fixed typo here
  }
}
