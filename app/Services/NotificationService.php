<?php

namespace App\Services;

use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Carbon\Carbon;

class NotificationService
{
  /**
   * $data keys:
   *   user_id (required)
   *   actor_user_id (optional)
   *   event_type (required)
   *   reference_table, reference_id (optional)
   *   payload (array) optional
   *   channel (optional)
   *   business_id (optional) - preferred explicit
   *   recipient_role (optional) - 'vendor'|'customer'|'both'|'system'
   *   is_global (optional) - boolean
   */
  public function createNotification(array $data): Notification
  {
    $payloadJson = array_key_exists('payload', $data) ? json_encode($data['payload']) : null;

    // explicit scope values — prefer direct input before checking payload
    $businessId = $data['business_id'] ?? null;
    $recipientRole = $data['recipient_role'] ?? null;
    $isGlobal = array_key_exists('is_global', $data) ? (bool)$data['is_global'] : false;

    // fallback: if business_id not explicitly provided, try payload
    if (!$businessId && is_array($data['payload'] ?? null) && array_key_exists('business_id', $data['payload'])) {
      $businessId = (int)$data['payload']['business_id'] ?: null;
      // if business found in payload, assume vendor recipient unless explicit given
      $recipientRole = $recipientRole ?? 'vendor';
      $isGlobal = $isGlobal ?? false;
    }

    // if still unknown, default recipient_role for customer notifications
    $recipientRole = $recipientRole ?? 'customer';
    // if is_global not provided and there is no business_id assume global
    if ($businessId === null) $isGlobal = $isGlobal ?? true;

    $notification = Notification::create([
      'user_id' => $data['user_id'],
      'actor_user_id' => $data['actor_user_id'] ?? null,
      'event_type' => $data['event_type'],
      'reference_table' => $data['reference_table'] ?? null,
      'reference_id' => $data['reference_id'] ?? null,
      'payload' => $payloadJson,
      'channel' => $data['channel'] ?? 'in_app',
      'is_read' => false,
      'created_at' => Carbon::now(),

      // new fields
      'business_id' => $businessId,
      'recipient_role' => $recipientRole,
      'is_global' => $isGlobal,
    ]);

    // dispatch job (non-blocking)
    SendNotificationJob::dispatch($notification->notification_id);

    return $notification;
  }
}
