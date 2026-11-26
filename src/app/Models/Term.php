<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a WordPress term's name and slug.
 */
class Term extends Model {
    /**
     * @var string
     */
    protected $table = 'terms';

    /**
     * @var string
     */
    protected $primaryKey = 'term_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'term_group' => 0,
    ];

    /**
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'term_group',
    ];

    /**
     * Gets the taxonomy records associated with this term.
     *
     * @return HasMany
     */
    public function taxonomies(): HasMany {
        return $this->hasMany(Taxonomy::class, 'term_id', 'term_id');
    }
}
