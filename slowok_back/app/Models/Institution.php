<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    use HasFactory;
    protected $table = 'institution';
    protected $primaryKey = 'institution_id';
    protected $fillable = ['name', 'type', 'contact_info', 'address', 'invite_code', 'is_active'];
    protected $casts = ['contact_info' => 'array', 'is_active' => 'boolean'];

    public function accounts()
    {
        return $this->hasMany(Account::class, 'institution_id', 'institution_id');
    }

    public function admins()
    {
        return $this->hasMany(Account::class, 'institution_id', 'institution_id')
            ->whereIn('role', [Account::ROLE_ADMIN, Account::ROLE_TEST]);
    }
}
