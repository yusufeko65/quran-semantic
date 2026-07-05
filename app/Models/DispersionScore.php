<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispersionScore extends Model
{
    protected $table = 'dispersion_scores';
    public $timestamps = false;
    protected $fillable = ['corpus_build_id','item_type','item_ref','juilland_d','dp',
        'top_surah_id','top_surah_share'];

    public function build(): BelongsTo { return $this->belongsTo(CorpusBuild::class, 'corpus_build_id'); }
}
