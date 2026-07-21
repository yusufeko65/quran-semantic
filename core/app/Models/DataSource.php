<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    protected $table = 'data_sources';
    public $timestamps = false;
    protected $fillable = ['name','version','url','license','category','qiraat','locked_at','notes'];
}
