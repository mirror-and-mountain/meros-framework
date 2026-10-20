<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class FormResponse extends Model {
    protected $table      = 'meros_form_responses';
    protected $primaryKey = 'id';

    protected $fillable = [
        'form_id',
        'response',
        'completed_actions',
        'status'
    ];

    protected $casts = [
        'response'          => 'encrypted:array',
        'completed_actions' => 'encrypted:array'
    ];
}