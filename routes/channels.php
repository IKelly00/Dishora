<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.business.{businessId}', function ($user, $businessId) {
  return true; // later add auth conditions if needed
});
