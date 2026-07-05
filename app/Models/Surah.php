<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Surah extends Model
{
    protected $table = 'surahs';
    public $timestamps = false;
    protected $fillable = ['id','name_arabic','transliteration','revelation_type','total_ayahs'];
    public $incrementing = false;

    public function ayahs(): HasMany { return $this->hasMany(Ayah::class); }
}
