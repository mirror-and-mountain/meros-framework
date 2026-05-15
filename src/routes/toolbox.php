<?php 

use Illuminate\Support\Facades\Route;
use MM\Meros\App\Middleware\AuthenticateAdmin;

Route::middleware(AuthenticateAdmin::class)->prefix('toolbox')->group(function() {
    Route::livewire('/', 'toolbox::index')->name('meros.toolbox.index');
    Route::livewire('/form-builder', 'toolbox::form-builder')->name('meros.toolbox.form-builder');
});

// Vite dev server proxy route - in development.
// Route::any('/vite-dev/{path}', function ($path) {
//     // The base path from your vite.config.js
//     $basePath = '/vendor/mirror-and-mountain/meros-framework/src/resources/vite/build';
    
//     // Construct the full URL Vite expects
//     $url = 'http://127.0.0.1:5173' . $basePath . '/' . ltrim($path, '/');
    
//     try {
//         $response = Http::timeout(10)->get($url);
        
//         return response($response->body(), $response->status())
//             ->header('Content-Type', $response->header('Content-Type') ?? 'text/javascript');
//     } catch (\Exception $e) {
//         return response('Vite dev server unavailable: ' . $e->getMessage(), 503);
//     }
// })->where('path', '.*');