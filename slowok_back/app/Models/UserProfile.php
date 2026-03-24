<?php
namespace App\Models;
use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;
    protected $table = 'user_profile';
    protected $primaryKey = 'profile_id';
    protected $fillable = [
        'account_id', 'name', 'phone', 'email', 'birth_date', 'user_type', 'parent_profile_id',
        'encrypted_name', 'encrypted_phone', 'encrypted_email', 'encrypted_birth_date', 'is_encrypted',
    ];
    protected $casts = [
        'birth_date' => 'date',
        'is_encrypted' => 'boolean',
    ];
    protected $hidden = ['encrypted_name', 'encrypted_phone', 'encrypted_email', 'encrypted_birth_date'];
    protected $appends = ['decrypted_name', 'decrypted_phone', 'decrypted_email'];

    public function getDecryptedNameAttribute(): ?string
    {
        if ($this->is_encrypted && $this->encrypted_name) {
            return app(EncryptionService::class)->decrypt($this->encrypted_name);
        }
        return $this->name;
    }

    public function getDecryptedPhoneAttribute(): ?string
    {
        if ($this->is_encrypted && $this->encrypted_phone) {
            return app(EncryptionService::class)->decrypt($this->encrypted_phone);
        }
        return $this->phone;
    }

    public function getDecryptedEmailAttribute(): ?string
    {
        if ($this->is_encrypted && $this->encrypted_email) {
            return app(EncryptionService::class)->decrypt($this->encrypted_email);
        }
        return $this->email;
    }

    public function account() { return $this->belongsTo(Account::class, 'account_id', 'account_id'); }
    public function parent() { return $this->belongsTo(UserProfile::class, 'parent_profile_id', 'profile_id'); }
    public function children() { return $this->hasMany(UserProfile::class, 'parent_profile_id', 'profile_id'); }
    public function screeningResults() { return $this->hasMany(ScreeningResult::class, 'profile_id', 'profile_id'); }
    public function curricula() { return $this->hasMany(Curriculum::class, 'profile_id', 'profile_id'); }
    public function learningProgress() { return $this->hasMany(LearningProgress::class, 'profile_id', 'profile_id'); }
    public function challengeAttempts() { return $this->hasMany(ChallengeAttempt::class, 'profile_id', 'profile_id'); }
    public function rewardCards() { return $this->belongsToMany(RewardCard::class, 'user_reward_card', 'profile_id', 'card_id')->withPivot('earned_at'); }
    public function assessments() { return $this->hasMany(Assessment::class, 'profile_id', 'profile_id'); }
    public function reports() { return $this->hasMany(LearningReport::class, 'profile_id', 'profile_id'); }
}
