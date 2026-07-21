<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionHistory extends Model
{
    protected $table = 'revision_histories';
    public $timestamps = false;
    protected $fillable = ['revisable_type','revisable_id','before_state','after_state',
        'trigger_reason','triggered_by_ayah_id','user_id','created_at'];
    protected $casts = ['before_state' => 'array', 'after_state' => 'array'];
}
