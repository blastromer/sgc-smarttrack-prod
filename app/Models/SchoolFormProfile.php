<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolFormProfile extends Model
{
    protected $fillable = [
        'school_code',
        'region',
        'division',
        'school_name',
        'school_address',
        'school_year',
        'contact',
        'email',
        'co_chair_elected',
        'co_chair_designated',
        'secretary_name',
        'school_head_name',
        'venue',
        'meeting_subject',
        'meeting_datetime',
        'meeting_purpose',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'region' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'school_address' => ['nullable', 'string', 'max:500'],
            'school_year' => ['nullable', 'string', 'max:40'],
            'contact' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'string', 'max:255'],
            'co_chair_elected' => ['nullable', 'string', 'max:255'],
            'co_chair_designated' => ['nullable', 'string', 'max:255'],
            'secretary_name' => ['nullable', 'string', 'max:255'],
            'school_head_name' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'meeting_subject' => ['nullable', 'string', 'max:255'],
            'meeting_datetime' => ['nullable', 'string', 'max:255'],
            'meeting_purpose' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function forSchool(User $user): self
    {
        return static::query()->firstOrNew([
            'school_code' => $user->packetSchoolCode(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function toForm(User $user): array
    {
        $head = User::schoolTeam($user->school_code)->firstWhere('role', 'school_head');

        return [
            'region' => (string) ($this->region ?? ''),
            'division' => (string) ($this->division ?? ''),
            'school_name' => (string) ($this->school_name ?: $user->school_name ?: $head?->school_name ?: ''),
            'school_address' => (string) ($this->school_address ?? ''),
            'school_year' => (string) ($this->school_year ?? ''),
            'contact' => (string) ($this->contact ?? ''),
            'email' => (string) ($this->email ?? ''),
            'co_chair_elected' => (string) ($this->co_chair_elected ?? ''),
            'co_chair_designated' => (string) ($this->co_chair_designated ?? ''),
            'secretary_name' => (string) ($this->secretary_name ?? ''),
            'school_head_name' => (string) ($this->school_head_name ?: $head?->name ?: ''),
            'venue' => (string) ($this->venue ?? ''),
            'meeting_subject' => (string) ($this->meeting_subject ?? ''),
            'meeting_datetime' => (string) ($this->meeting_datetime ?? ''),
            'meeting_purpose' => (string) ($this->meeting_purpose ?? ''),
        ];
    }

    public function filledCount(): int
    {
        return collect($this->only($this->fillable))
            ->except(['school_code'])
            ->filter(fn ($value) => filled($value))
            ->count();
    }
}
