<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisCache extends Model
{
    protected $table = 'analysis_caches';
    public $timestamps = false;
    protected $fillable = ['subject_type','subject_id','content','verdict','model_version',
        'input_ayah_ids','generated_by_run_id','is_current','created_at'];
    protected $casts = ['content' => 'array', 'input_ayah_ids' => 'array', 'is_current' => 'boolean'];

    public function run(): BelongsTo { return $this->belongsTo(AiRunLog::class, 'generated_by_run_id'); }
}
