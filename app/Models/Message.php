<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
  use HasFactory;

  protected $table = 'messages';
  protected $primaryKey = 'message_id';
  public $timestamps = false;

  protected $fillable = [
    'sender_id',
    'sender_role',
    'receiver_id',
    'receiver_role',
    'message_text',
    'sent_at',
    'is_read',
  ];

  protected $casts = [
    'sent_at' => 'datetime',
    'is_read' => 'boolean',
  ];
}
