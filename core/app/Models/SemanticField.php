<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemanticField extends Model
{
    protected $table = 'semantic_fields';
    public $timestamps = false;
    protected $fillable = ['name','description','status','created_by','created_at'];

    public function members(): HasMany { return $this->hasMany(SemanticFieldMember::class); }
    public function confirmedMembers(): HasMany
    {
        return $this->hasMany(SemanticFieldMember::class)->where('status', 'confirmed');
    }
}
