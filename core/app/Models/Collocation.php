<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collocation extends Model
{
    protected $table = 'collocations';
    public $timestamps = false;
    protected $fillable = ['corpus_build_id','variant','unit','item_type','item_a','item_b',
        'n_a','n_b','n_ab','n_ab_first_instance','n_total','n_scope','expected','pmi','g2',
        'p_permutation','fdr_significant','top_surah_id','top_surah_share'];
    protected $casts = ['fdr_significant' => 'boolean'];

    public function build(): BelongsTo { return $this->belongsTo(CorpusBuild::class, 'corpus_build_id'); }
}
