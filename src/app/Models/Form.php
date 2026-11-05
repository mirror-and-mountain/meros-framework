<?php

namespace MM\Meros\App\Models;

class Form extends Post {
    protected $attributes = [
        'post_author'           => 0,
        'post_date'             => '1970-01-01 00:00:00',
        'post_date_gmt'         => '1970-01-01 00:00:00',
        'post_content'          => '',
        'post_title'            => '',
        'post_excerpt'          => '',
        'post_status'           => 'draft',
        'comment_status'        => 'closed',
        'ping_status'           => 'closed',
        'post_password'         => '',
        'post_name'             => '',
        'to_ping'               => '',
        'pinged'                => '',
        'post_modified'         => '1970-01-01 00:00:00',
        'post_modified_gmt'     => '1970-01-01 00:00:00',
        'post_content_filtered' => '',
        'post_parent'           => 0,
        'guid'                  => '',
        'menu_order'            => 0,
        'post_type'             => 'form',
        'post_mime_type'        => '',
        'comment_count'         => 0,
    ];

    protected static function booted() {
        static::addGlobalScope('form', function ($query) {
            $query->where('post_type', 'form');
        });
    }
}