<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model {
    protected $table      = 'options';
    protected $primaryKey = 'option_id';
    public    $timestamps =  false;

    protected $fillable = [
        'option_name',
        'option_value'
    ];
}