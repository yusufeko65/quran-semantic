<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Word extends Model
{
    protected $table = 'words';
    public $timestamps = false;
    protected $fillable = ['ayah_id','position_in_ayah','text_uthmani','text_normalized','root_id',
        'lemma','pos','wazan','morph_features','segments','qac_location','data_source_id'];
    protected $casts = ['morph_features' => 'array', 'segments' => 'array'];

    public function ayah(): BelongsTo { return $this->belongsTo(Ayah::class); }
    public function glosses(): HasMany { return $this->hasMany(WordGloss::class); }

    public function root(): BelongsTo { return $this->belongsTo(Root::class); }
}
