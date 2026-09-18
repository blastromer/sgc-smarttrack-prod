<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cycle extends Model
{
    protected $fillable = [
        'name',
        'level',
        'opens_at',
        'deadline_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'date',
            'deadline_at' => 'date',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && $this->opens_at->startOfDay()->lte(now())
            && $this->deadline_at->endOfDay()->gte(now());
    }
}
