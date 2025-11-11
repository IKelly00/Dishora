<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $notificationId;

  public function __construct(int $notificationId)
  {
    $this->notificationId = $notificationId;
  }

  public function handle()
  {
    $notification = Notification::find($this->notificationId);
    if (!$notification) return;

    $userId = $notification->user_id;
    $eventType = $notification->event_type;

    // In-app delivery log
    try {
      NotificationDelivery::create([
        'notification_id' => $notification->notification_id,
        'provider' => 'system',
        'provider_response' => 'Stored in DB for in-app delivery',
        'success' => true,
      ]);
    } catch (\Throwable $e) {
      Log::error('[SendNotificationJob] delivery log failed', ['err' => $e->getMessage()]);
    }

    // Placeholder push logic: logs and creates placeholder delivery records
    $prefPush = NotificationPreference::where('user_id', $userId)
      ->where('event_type', $eventType)
      ->where('channel', 'push')
      ->first();

    $pushEnabled = $prefPush ? (bool)$prefPush->enabled : true;

    if ($pushEnabled) {
      $tokens = DeviceToken::where('user_id', $userId)->where('is_active', true)->get();
      foreach ($tokens as $token) {
        try {
          Log::info('[SendNotificationJob] push placeholder', [
            'notification_id' => $notification->notification_id,
            'token' => $token->token,
            'provider' => $token->provider
          ]);

          NotificationDelivery::create([
            'notification_id' => $notification->notification_id,
            'provider' => $token->provider ?? 'unknown',
            'provider_response' => 'Simulated push (no provider configured)',
            'success' => null,
          ]);
        } catch (\Throwable $e) {
          NotificationDelivery::create([
            'notification_id' => $notification->notification_id,
            'provider' => $token->provider ?? 'unknown',
            'provider_response' => $e->getMessage(),
            'success' => false,
          ]);
        }
      }
    }
  }
}
