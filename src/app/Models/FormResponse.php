<?php 

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormResponse extends Model {
    protected $table = 'meros_form_responses';
    protected $primaryKey = 'id';

    protected $fillable = [
        'form_id',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];

    public function form(): BelongsTo {
        return $this->belongsTo(Form::class, 'form_id');
    }
}