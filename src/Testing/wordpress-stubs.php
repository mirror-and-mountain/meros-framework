<?php

function is_admin() { return false; }
function trailingslashit($value) { return rtrim($value, '/\\') . '/'; }
function get_template_directory_uri() { return 'https://meros.test/theme'; }
function get_current_user_id() { return 0; }
function get_user_by($field, $value) { return false; }
function get_the_ID() { return false; }
function wp_kses_post($value) { return $value; }
