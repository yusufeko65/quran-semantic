<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestVerse extends Model
{
    protected $table = 'test_verses';
    public $timestamps = false;
    protected $fillable = ['hypothesis_id','ayah_id','role','is_muhkam_anchor','retrieval_layer','note'];
    protected $casts = ['is_muhkam_anchor' => 'boolean'];

    public function hypothesis(): BelongsTo { return $this->belongsTo(Hypothesis::class); }
    public function ayah(): BelongsTo { return $this->belongsTo(Ayah::class); }
}
