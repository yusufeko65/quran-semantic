<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorpusBuild extends Model
{
    protected $table = 'corpus_builds';
    public $timestamps = false;
    protected $fillable = ['description','data_source_ids','script_hash','built_at'];
    protected $casts = ['data_source_ids' => 'array'];
}
