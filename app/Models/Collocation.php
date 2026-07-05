<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collocation extends Model
{
    protected $table = 'collocations';
    public $timestamps = false;
    protected $fillable = ['corpus_build_id','unit','item_type','item_a','item_b',
        'n_a','n_b','n_ab','n_total','expected','pmi','g2','p_permutation','fdr_significant'];
    protected $casts = ['fdr_significant' => 'boolean'];

    public function build(): BelongsTo { return $this->belongsTo(CorpusBuild::class, 'corpus_build_id'); }
}
