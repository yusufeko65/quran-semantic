<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Phoneme extends Model
{
    protected $table = 'phonemes';
    public $timestamps = false;
    protected $fillable = ['letter','letter_name','ipa','makhraj','sifat','character_desc'];
    protected $casts = ['sifat' => 'array'];
}
