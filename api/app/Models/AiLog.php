<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'task_type',
        'resource_type',
        'resource_id',
        'prompt_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'latency_ms',
        'status',
        'error_message',
        'output_preview',
        'created_at',
    ];

    protected $casts = [
        'prompt_tokens'     => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens'      => 'integer',
        'cost_usd'          => 'decimal:6',
        'latency_ms'        => 'integer',
        'created_at'        => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiLog $log) {
            $log->created_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class, 'prompt_id');
    }
}
