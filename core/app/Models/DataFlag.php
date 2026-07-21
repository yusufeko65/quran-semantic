<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataFlag extends Model
{
    protected $table = 'data_flags';
    public $timestamps = false;
    protected $fillable = ['flaggable_type','flaggable_id','field_name','current_value',
        'proposed_value','reason','proposed_by','status','reviewed_by','created_at','reviewed_at'];
}
