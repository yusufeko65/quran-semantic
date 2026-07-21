<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AyahClassification extends Model
{
    protected $table = 'ayah_classifications';
    public $timestamps = false;
    protected $fillable = ['ayah_id','classification','source_id','is_current','notes','created_at'];
    protected $casts = ['is_current' => 'boolean'];

    public function ayah(): BelongsTo { return $this->belongsTo(Ayah::class); }
    public function source(): BelongsTo { return $this->belongsTo(DataSource::class, 'source_id'); }
}
