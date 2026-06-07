<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'avatar',
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'google_id',
        'locked',
        'status',
        'email_verified',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'login_count',
        'ban_reason',
        'birth_of_date',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'birth_of_date' => 'date',
            'locked' => 'boolean',
            'email_verified' => 'boolean',
            'last_login_at' => 'datetime',
            'login_count' => 'integer',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'account_roles', 'account_id', 'role_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'account_id');
    }

    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class, 'account_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'account_id');
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'account_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'account_id');
    }
}
