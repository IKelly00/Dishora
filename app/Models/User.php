<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Role;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

class User extends Authenticatable implements MustVerifyEmail
{
  use HasFactory, Notifiable;

  protected $primaryKey = 'user_id';
  //protected $with = ['roles'];

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'fullname',
    'username',
    'email',
    'password',
    'email_verified_at',
    'is_verified'
  ];


  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
    ];
  }

  /**
   * Check if the user is THE Super Admin based on ID and username.
   *
   * @return bool
   */
  public function isTheSuperAdmin(): bool
  {
    // The condition you specified: user_id is 1 AND username is 'admin'
    return (int)$this->user_id === 1 && $this->username === 'admin';
  }

  // Override the sendEmailVerificationNotification and makes the email token expire in 60min
  public function sendEmailVerificationNotification()
  {
    $this->notify(new class($this) extends VerifyEmail {
      protected function verificationUrl($notifiable)
      {
        $url = URL::temporarySignedRoute(
          'verification.verify',
          Carbon::now()->addMinutes(60),
          [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
          ]
        );

        //\Log::info('[Email Verification Test] URL: ' . $url);

        return $url;
      }
    });
  }

  // Override the markEmailAsVerified and udpate email_verified_at and is_verified after user verify email
  public function markEmailAsVerified()
  {
    if ($this->hasVerifiedEmail()) {
      return false;
    }

    return $this->forceFill([
      'email_verified_at' => $this->freshTimestamp(),
      'is_verified' => true,
    ])->save();
  }

  public function customer()
  {
    return $this->hasOne(Customer::class, 'user_id', 'user_id');
  }

  public function vendor()
  {
    return $this->hasOne(Vendor::class, 'user_id', 'user_id');
  }

  public function notifications()
  {
    return $this->hasMany(\App\Models\Notification::class, 'user_id', 'user_id');
  }

  public function deviceTokens()
  {
    return $this->hasMany(\App\Models\DeviceToken::class, 'user_id', 'user_id');
  }

  public function notificationPreferences()
  {
    return $this->hasMany(\App\Models\NotificationPreference::class, 'user_id', 'user_id');
  }
}
