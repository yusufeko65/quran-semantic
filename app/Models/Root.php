<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Root extends Model
{
    protected $table = 'roots';
    public $timestamps = false;
    protected $fillable = ['arabic','transliteration','letter_count','base_meaning',
        'proto_semitic_form','proto_semitic_meaning','proto_semitic_source_id'];

    public function words(): HasMany { return $this->hasMany(Word::class); }
    public function protoSemiticSource(): BelongsTo { return $this->belongsTo(DataSource::class, 'proto_semitic_source_id'); }
}
