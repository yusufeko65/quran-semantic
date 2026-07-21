<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TafsirEntry extends Model
{
    protected $table = 'tafsir_entries';
    public $timestamps = false;
    protected $fillable = ['tafsir_source_id','ayah_id','text'];

    public function source(): BelongsTo { return $this->belongsTo(TafsirSource::class, 'tafsir_source_id'); }
    public function ayah(): BelongsTo { return $this->belongsTo(Ayah::class); }
}
