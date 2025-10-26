<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'messages';

  /**
   * The primary key associated with the table.
   * (Laravel's default is 'id', yours is 'message_id')
   *
   * @var string
   */
  protected $primaryKey = 'message_id';

  /**
   * Indicates if the model should be timestamped.
   * (Set to false because you have 'sent_at' but not 'updated_at')
   *
   * @var bool
   */
  public $timestamps = false;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'sender_id',
    'receiver_id',
    'message_text',
    'sent_at',
    'is_read',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'sent_at' => 'datetime',
    'is_read' => 'boolean',
  ];

  /**
   * Get the user who sent the message.
   */
  public function sender()
  {
    // Links this model's 'sender_id' to the 'users' table's 'user_id'
    return $this->belongsTo(User::class, 'sender_id', 'user_id');
  }

  /**
   * Get the user who received the message.
   */
  public function receiver()
  {
    // Links this model's 'receiver_id' to the 'users' table's 'user_id'
    return $this->belongsTo(User::class, 'receiver_id', 'user_id');
  }
}
