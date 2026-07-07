<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    protected $table = 'translations';
    public $timestamps = false;
    protected $fillable = ['ayah_id','source_id','lang','text','created_at'];

    public function ayah(): BelongsTo { return $this->belongsTo(Ayah::class); }
    public function source(): BelongsTo { return $this->belongsTo(DataSource::class, 'source_id'); }
}
