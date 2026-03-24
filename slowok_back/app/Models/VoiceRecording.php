<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceRecording extends Model
{
    protected $table = 'voice_recording';
    protected $primaryKey = 'recording_id';

    protected $fillable = [
        'profile_id',
        'assignable_type',
        'assignable_id',
        'file_path',
        'file_url',
        'original_name',
        'file_size',
        'duration',
        'memo',
        'stt_text',
        'stt_confidence',
        'question_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'profile_id', 'profile_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(RecordingFeedback::class, 'recording_id', 'recording_id');
    }
}
