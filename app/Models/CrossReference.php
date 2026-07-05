<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossReference extends Model
{
    protected $table = 'cross_references';
    public $timestamps = false;
    protected $fillable = ['ayah_a_id','ayah_b_id','relation_type','status','proposed_source',
        'seed_source_id','similarity','rationale','proposed_by','confirmed_by','created_at','confirmed_at'];

    public function ayahA(): BelongsTo { return $this->belongsTo(Ayah::class, 'ayah_a_id'); }
    public function ayahB(): BelongsTo { return $this->belongsTo(Ayah::class, 'ayah_b_id'); }
}
