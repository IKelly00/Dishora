<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log; // Add Log facade

class MessageSent implements ShouldBroadcast
{
  use InteractsWithSockets, SerializesModels;

  public Message $message;
  public ?int $businessId = null; // Initialize businessId as nullable int

  // Define constants consistent with your controller
  const BIZ_PREFIX = '[BIZ_ID::';
  const BIZ_SUFFIX = ']';

  public function __construct(Message $message)
  {
    // Extract business ID from the message text
    if (str_starts_with($message->message_text, self::BIZ_PREFIX)) {
      $endPos = strpos($message->message_text, self::BIZ_SUFFIX);
      if ($endPos !== false) {
        $idStr = substr(
          $message->message_text,
          strlen(self::BIZ_PREFIX),
          $endPos - strlen(self::BIZ_PREFIX)
        );
        // Ensure it's a valid integer
        if (ctype_digit($idStr)) {
          $this->businessId = (int)$idStr;
        }
      }
    }

    // --- Important: Prepare the message for broadcasting ---
    // 1. Load sender relationship
    $message->load('sender:user_id,fullname');

    // 2. Temporarily CLEAN the message text (remove prefix) for the broadcast payload
    //    (We clone to avoid modifying the original object before the AJAX response)
    $messageForBroadcast = clone $message;
    if ($this->businessId !== null) {
      $messageForBroadcast->message_text = str_replace(
        self::BIZ_PREFIX . $this->businessId . self::BIZ_SUFFIX,
        '',
        $message->message_text // Use original message text here
      );
    }
    $this->message = $messageForBroadcast; // Use the cleaned message for broadcasting

    Log::debug('MessageSent Event Initialized', [ // Log details
      'original_text' => $message->message_text,
      'extracted_business_id' => $this->businessId,
      'broadcast_message_text' => $this->message->message_text,
      'sender_loaded' => $this->message->relationLoaded('sender')
    ]);
  }

  public function broadcastOn(): ?Channel // Return nullable Channel
  {
    // Only broadcast if we successfully extracted a business ID
    if ($this->businessId !== null) {
      Log::debug('Broadcasting on channel:', ['channel' => 'chat.business.' . $this->businessId]); // Log channel
      return new Channel('chat.business.' . $this->businessId);
    }
    Log::warning('MessageSent Event: Could not extract business ID. Not broadcasting.'); // Log failure
    return null; // Return null if no businessId, preventing broadcast
  }

  public function broadcastWith(): array
  {
    // The message is already prepared (cleaned text, loaded sender) in the constructor
    Log::debug('Broadcasting with payload:', ['message' => $this->message->toArray()]); // Log payload
    return ['message' => $this->message->toArray()];
  }

  // Optional: Define a broadcast channel alias if using Echo
  // public function broadcastAs()
  // {
  //     return 'message.sent'; // Example alias
  // }
}
