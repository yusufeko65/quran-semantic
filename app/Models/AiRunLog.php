<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRunLog extends Model
{
    protected $table = 'ai_run_logs';
    public $timestamps = false;
    protected $fillable = ['purpose','model','requested_by','hypothesis_id','input_snapshot',
        'retrieved_ayah_ids','output','grounding_check','rejected_reason',
        'input_tokens','output_tokens','cost_usd','created_at'];
    protected $casts = ['input_snapshot' => 'array', 'retrieved_ayah_ids' => 'array', 'output' => 'array'];
}
