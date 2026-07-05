<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TafsirSource extends Model
{
    protected $table = 'tafsir_sources';
    public $timestamps = false;
    protected $fillable = ['name','author','era','language','data_source_id'];

    public function entries(): HasMany { return $this->hasMany(TafsirEntry::class); }
}
