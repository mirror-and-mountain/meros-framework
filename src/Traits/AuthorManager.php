<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

trait AuthorManager
{
    protected array $authors = [];

    public function addAuthor( string|array $author ): bool|string
    {
        $authorName  = ''; // Author name
        $description = ''; // Author description
        $support     = ''; // Author support link/email
        $link        = ''; // Author url

        // Sanitize author info
        if ( is_array( $author ) ) {
            
            $authorName  = $author['name'] ? Str::slug( $author['name'], '_' ) : '';
            $description = $author['description'] ?? '';
            $support     = $author['support'] ?? '';
            $link        = $author['link'] ?? '';
            
            // Return false if we don't have an author name
            if ( $authorName === '' ) {
                return false;
            }

        } else {

            $authorName = Str::slug( $author, '_' );

        }

        $authorInfo = [
            'name'        => $authorName,
            'description' => $description,
            'support'     => $support,
            'link'        => $link
        ];

        if ( !array_key_exists( $authorName, $this->authors ) ) {
            $this->authors[ $authorName ] = $authorInfo;
        }

        return $authorName;
    }

    public function getAuthors(): array 
    {
        return $this->authors;
    }

    public function getAuthor( string $name ): ?array
    {
        return $this->authors[ $name ] ?? null;
    }

    public function getAuthorFeatures( string $author ): ?array
    {
        $features = Arr::undot( $this->features );

        return $features[ $author ];
    }
}