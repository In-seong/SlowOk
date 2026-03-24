<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $table = 'feature';
    protected $primaryKey = 'feature_id';
    protected $fillable = ['feature_key', 'name', 'description', 'category', 'sort_order'];
}
