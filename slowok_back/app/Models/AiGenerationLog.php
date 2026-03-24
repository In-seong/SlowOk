<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGenerationLog extends Model
{
    protected $table = 'ai_generation_log';
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'account_id',
        'institution_id',
        'prompt',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'status',
        'error_message',
    ];
}
