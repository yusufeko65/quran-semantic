<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemanticFieldMember extends Model
{
    protected $table = 'semantic_field_members';
    public $timestamps = false;
    protected $fillable = ['semantic_field_id','member_type','root_id','lemma','status',
        'proposed_source','cluster_score','proposed_by','confirmed_by','created_at','confirmed_at'];

    public function field(): BelongsTo { return $this->belongsTo(SemanticField::class, 'semantic_field_id'); }
    public function root(): BelongsTo { return $this->belongsTo(Root::class); }
}
