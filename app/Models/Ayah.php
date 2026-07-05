<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ayah extends Model
{
    protected $table = 'ayahs';
    public $timestamps = false;
    protected $fillable = ['surah_id','number_in_surah','text_uthmani','text_imlaei','text_normalized','data_source_id'];

    public function surah(): BelongsTo { return $this->belongsTo(Surah::class); }
    public function words(): HasMany { return $this->hasMany(Word::class)->orderBy('position_in_ayah'); }
    public function classifications(): HasMany { return $this->hasMany(AyahClassification::class); }

    /** Klasifikasi muhkamat/mutasyabihat yang berlaku saat ini (§6). */
    public function currentClassification()
    {
        return $this->hasOne(AyahClassification::class)->where('is_current', true);
    }

    /** Referensi ayat standar, mis. "2:255". */
    public function getRefAttribute(): string
    {
        return $this->surah_id . ':' . $this->number_in_surah;
    }
}
