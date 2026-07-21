<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hypothesis extends Model
{
    protected $table = 'hypotheses';
    public $timestamps = false;
    protected $fillable = ['parent_id','statement','subject_type','subject_ref','registration',
        'operational_definition','status','methodological_flag','proposed_by','created_at'];
    protected $casts = ['methodological_flag' => 'boolean'];

    public function parent(): BelongsTo { return $this->belongsTo(Hypothesis::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Hypothesis::class, 'parent_id'); }
    public function verdicts(): HasMany { return $this->hasMany(Verdict::class); }
    public function currentVerdict()
    {
        return $this->hasOne(Verdict::class)->where('is_current', true);
    }
    public function testVerses(): HasMany { return $this->hasMany(TestVerse::class); }
}
