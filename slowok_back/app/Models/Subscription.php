<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;
    protected $table = 'subscription';
    protected $primaryKey = 'subscription_id';
    protected $fillable = ['account_id', 'plan_type', 'status', 'started_at', 'expires_at'];
    protected $casts = ['started_at' => 'datetime', 'expires_at' => 'datetime'];

    public function account() { return $this->belongsTo(Account::class, 'account_id', 'account_id'); }
}
