<?php

namespace App\Models;

use App\Support\FatCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = [
        'user_id',
        'cycle_id',
        'school_code',
        'status',
        'result',
        'qa_certified_at',
        'submitted_at',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'qa_certified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Assessment $assessment) {
            foreach (FatCatalog::indicators() as $indicator) {
                $assessment->indicators()->create([
                    'code' => $indicator['code'],
                    'title' => $indicator['title'],
                ]);
            }

            $assessment->movs()->create([
                'indicator_code' => null,
                'code' => 'Validity',
                'title' => 'Validity Form',
                'kind' => 'validity',
                'status' => 'draft',
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(IndicatorAnswer::class);
    }

    public function movs(): HasMany
    {
        return $this->hasMany(Mov::class);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['submitted', 'under_review', 'validated'], true);
    }

    public function answersLocked(): bool
    {
        return $this->submitted_at !== null || $this->isLocked();
    }

    public function canReplaceMov(Mov $mov): bool
    {
        if ($this->status === 'validated') {
            return false;
        }

        if ($this->isLocked()) {
            return $mov->status === 'returned';
        }

        if ($this->submitted_at === null) {
            return true;
        }

        return in_array($mov->status, ['draft', 'uploaded', 'returned'], true);
    }

    public function canRemoveMov(Mov $mov): bool
    {
        if (! $mov->hasFile()) {
            return false;
        }

        if ($this->status === 'validated' || $mov->status === 'valid') {
            return false;
        }

        if ($this->isLocked()) {
            return false;
        }

        return true;
    }

    public function canWithdraw(): bool
    {
        if (! in_array($this->status, ['submitted', 'under_review'], true)) {
            return false;
        }

        return ! $this->movs()->where('status', 'valid')->exists();
    }
}
