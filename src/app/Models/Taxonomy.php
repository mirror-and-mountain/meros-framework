<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Represents a WordPress term's membership in a named taxonomy.
 */
class Taxonomy extends Model {
    /**
     * @var string
     */
    protected $table = 'term_taxonomy';

    /**
     * @var string
     */
    protected $primaryKey = 'term_taxonomy_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'description' => '',
        'parent' => 0,
        'count' => 0,
    ];

    /**
     * @var array<string>
     */
    protected $fillable = [
        'term_id',
        'taxonomy',
        'description',
        'parent',
        'count',
    ];

    /**
     * Gets the term containing this taxonomy record's name and slug.
     *
     * @return BelongsTo
     */
    public function term(): BelongsTo {
        return $this->belongsTo(Term::class, 'term_id', 'term_id');
    }

    /**
     * Gets the posts assigned to this taxonomy record.
     *
     * @return BelongsToMany
     */
    public function posts(): BelongsToMany {
        return $this->belongsToMany(
            Post::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id',
            'term_taxonomy_id',
            'ID'
        )->withPivot('term_order');
    }
}
