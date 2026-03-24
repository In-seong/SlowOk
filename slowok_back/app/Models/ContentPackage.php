<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPackage extends Model
{
    use Traits\BelongsToInstitution;

    protected $table = 'content_package';
    protected $primaryKey = 'package_id';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'is_active',
        'institution_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ContentPackageItem::class, 'package_id', 'package_id')
            ->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(Account::class, 'created_by', 'account_id');
    }
}
