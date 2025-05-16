<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Arr;

trait AuthorManager
{
    final public function getAuthorFeatures( string $author ): ?array
    {
        $features = Arr::undot( $this->features );

        return $features[ $author ];
    }
}