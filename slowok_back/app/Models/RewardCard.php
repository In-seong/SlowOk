<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardCard extends Model
{
    use HasFactory, Traits\BelongsToInstitution;
    protected $table = 'reward_card';
    protected $primaryKey = 'card_id';
    protected $fillable = ['name', 'description', 'image_url', 'rarity', 'institution_id', 'is_active'];

    public function users() { return $this->belongsToMany(UserProfile::class, 'user_reward_card', 'card_id', 'profile_id')->withPivot('earned_at'); }
}
