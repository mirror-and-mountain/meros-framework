<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Arr;

trait AuthorManager
{
    /**
     * Helper to get features by author name.
     *
     * @param  string     $author
     * @return array|null
     */
    final public function getAuthorFeatures( string $author ): ?array
    {
        $features = Arr::undot( $this->features );

        return $features[ $author ];
    }
}