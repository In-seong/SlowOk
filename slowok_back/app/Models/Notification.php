<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $table = 'notification';
    protected $primaryKey = 'notification_id';
    protected $fillable = ['account_id', 'title', 'message', 'type', 'is_read'];
    protected $casts = ['is_read' => 'boolean'];

    public function account() { return $this->belongsTo(Account::class, 'account_id', 'account_id'); }
}
