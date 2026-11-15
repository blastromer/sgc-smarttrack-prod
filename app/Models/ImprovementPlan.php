<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementPlan extends Model
{
    protected $table = 'governance_improvement_plans';

    protected $fillable = [
        'school_code',
        'cycle_id',
        'assessment_id',
        'version',
        'status',
        'priority',
        'summary',
        'objectives',
        'actions',
        'source_fingerprint',
        'model',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'actions' => 'array',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coachingSessions(): HasMany
    {
        return $this->hasMany(CoachingSession::class, 'plan_id');
    }
}
