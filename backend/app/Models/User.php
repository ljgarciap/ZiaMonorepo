<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
    /**
     * Get the companies associated with the user.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    /**
     * Check if the user is a global administrator.
     */
    public function isGlobalAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'admin']);
    }

    /**
     * Check if the user can manage a specific role.
     */
    public function canManageRole(string $targetRole): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        if ($this->role === 'admin') {
            return $targetRole === 'user';
        }

        return false;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
