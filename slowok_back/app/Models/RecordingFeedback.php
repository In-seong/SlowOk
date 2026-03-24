<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordingFeedback extends Model
{
    protected $table = 'recording_feedback';
    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'recording_id',
        'account_id',
        'comment',
    ];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(VoiceRecording::class, 'recording_id', 'recording_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
}
