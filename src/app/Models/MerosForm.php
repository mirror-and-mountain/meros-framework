<?php

namespace MM\Meros\App\Models;

use MM\Meros\Facades\PostTypes;

class MerosForm extends Post {
    /**
     * Scope the query to only include posts of the 'meros-form' post type.
     *
     * @return void
     */
    public function newQuery() {
        return parent::newQuery()->where('post_type', 'meros-form');
    }

    /**
     * Get the form structure schema, either as a JSON string or as an associative array.
     *
     * @param boolean $asArray
     *
     * @return string|array
     */
    public function schema(bool $asArray = false): string|array {
        $schema = null;
        $meta = null;

        try {
            $meta = $this->meta->where('meta_key', '_meros_form_meta')->first();
        } catch (\Exception $e) {
            return $asArray ? [] : json_encode([]);
        }

        if ($meta !== null) {
            $schema = json_decode($meta->meta_value, true)['schema'] ?? null;
        }

        if ($schema) {
            return $asArray ?  $schema : json_encode($schema);
        }
        
        $form = PostTypes::get('meros-form');

        $schema = $form->meta('default', 'schema')->getDefault();
        return $asArray ? json_decode($schema, true) : $schema;
    }
}