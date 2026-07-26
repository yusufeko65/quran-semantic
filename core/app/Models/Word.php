<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Word extends Model
{
    protected $table = 'words';
    public $timestamps = false;
    // FASE 4 (KICKOFF-FASE4 §2.2): 'wazan' DIHAPUS dari fillable — kolom
    // sudah di-DROP dari skema (migration 2026_07_23_000019). Kolom mati
    // sejak impor awal (77.429/77.429 NULL), redundan thd morph_features.
    protected $fillable = ['ayah_id','position_in_ayah','text_uthmani','text_normalized','root_id',
        'lemma','pos','morph_features','segments','qac_location','data_source_id'];
    protected $casts = ['morph_features' => 'array', 'segments' => 'array'];

    public function ayah(): BelongsTo { return $this->belongsTo(Ayah::class); }
    public function glosses(): HasMany { return $this->hasMany(WordGloss::class); }

    public function root(): BelongsTo { return $this->belongsTo(Root::class); }
}
