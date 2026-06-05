<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPrompt extends Model
{
    protected $fillable = [
        'name',
        'version',
        'task_type',
        'model_hint',
        'system_message',
        'user_template',
        'output_format',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version'   => 'integer',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AiLog::class, 'prompt_id');
    }
}
