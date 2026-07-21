<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordGloss extends Model
{
    protected $table = 'word_glosses';
    public $timestamps = false;
    protected $fillable = ['word_id','source_id','lang','gloss','created_at'];

    public function word(): BelongsTo { return $this->belongsTo(Word::class); }
    public function source(): BelongsTo { return $this->belongsTo(DataSource::class, 'source_id'); }
}
