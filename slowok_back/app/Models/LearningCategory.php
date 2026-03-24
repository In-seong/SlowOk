<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningCategory extends Model
{
    use HasFactory, Traits\BelongsToInstitution;
    protected $table = 'learning_category';
    protected $primaryKey = 'category_id';
    protected $fillable = ['name', 'description', 'icon', 'institution_id', 'is_active'];

    public function screeningTests() { return $this->hasMany(ScreeningTest::class, 'category_id', 'category_id'); }
    public function contents() { return $this->hasMany(LearningContent::class, 'category_id', 'category_id'); }
    public function challenges() { return $this->hasMany(Challenge::class, 'category_id', 'category_id'); }
    public function curricula() { return $this->hasMany(Curriculum::class, 'category_id', 'category_id'); }
    public function assessments() { return $this->hasMany(Assessment::class, 'category_id', 'category_id'); }
}
