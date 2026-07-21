<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HypothesisConfidence extends Model
{
    protected $table = 'hypothesis_confidences';
    public $timestamps = false;
    protected $primaryKey = 'hypothesis_id';
    public $incrementing = false;
    protected $fillable = ['hypothesis_id','tested_verses_count','posterior','updated_at'];
}
