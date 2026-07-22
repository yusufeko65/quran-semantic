<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisCache extends Model
{
    protected $table = 'analysis_caches';
    public $timestamps = false;
    protected $fillable = ['subject_type','subject_id','subject_ref','content','verdict','model_version',
        'input_ayah_ids','generated_by_run_id','is_current','promoted_at','promoted_by','created_at'];
    protected $casts = ['content' => 'array', 'input_ayah_ids' => 'array', 'is_current' => 'boolean',
        'promoted_at' => 'datetime'];

    public function run(): BelongsTo { return $this->belongsTo(AiRunLog::class, 'generated_by_run_id'); }
}
