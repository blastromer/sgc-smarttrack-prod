<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mov extends Model
{
    protected $fillable = [
        'assessment_id',
        'indicator_code',
        'code',
        'title',
        'kind',
        'original_name',
        'path',
        'mime',
        'size',
        'status',
        'return_reason',
        'removal_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'removal_requested_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    public function hasFile(): bool
    {
        return filled($this->path);
    }
}
