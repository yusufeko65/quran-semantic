<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verdict extends Model
{
    protected $table = 'verdicts';
    public $timestamps = false;
    protected $fillable = ['hypothesis_id','verdict','summary','missing_data','effect_size',
        'p_value','correction_method','ai_run_id','decided_by','is_current','created_at'];
    protected $casts = ['is_current' => 'boolean'];

    public function hypothesis(): BelongsTo { return $this->belongsTo(Hypothesis::class); }
}
