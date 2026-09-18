<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'office',
        'school_name',
        'school_code',
        'position',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'role_label',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function assessments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function isSchoolStaff(): bool
    {
        return in_array($this->role, ['school', 'school_head'], true);
    }

    public function isSchoolHead(): bool
    {
        return $this->role === 'school_head';
    }

    public function isEncoder(): bool
    {
        return $this->role === 'school';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'super' => 'Super Admin',
            'division' => 'Division Admin',
            'school_head' => 'School Head',
            default => 'Encoder',
        };
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            'super' => 'super.overview',
            'division' => 'division.overview',
            default => 'school.dashboard',
        };
    }

    public function packetSchoolCode(): string
    {
        return filled($this->school_code) ? (string) $this->school_code : 'user-'.$this->id;
    }

    public static function schoolTeam(?string $schoolCode)
    {
        if (! filled($schoolCode) || str_starts_with((string) $schoolCode, 'user-')) {
            return collect();
        }

        return static::query()
            ->where('school_code', $schoolCode)
            ->whereIn('role', ['school', 'school_head'])
            ->where('status', 'active')
            ->get();
    }
}
