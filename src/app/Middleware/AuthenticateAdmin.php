<?php

namespace MM\Meros\App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate admin users for protected routes.
 */
class AuthenticateAdmin {

    public function handle(Request $request, Closure $next): Response {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url($request->fullUrl()));
            exit;
        }

        if (!current_user_can('manage_options')) {
            wp_redirect(wp_login_url($request->fullUrl()));
            exit;
        }

        return $next($request);
    }
}