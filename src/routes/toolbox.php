<?php 

use Illuminate\Support\Facades\Route;
use MM\Meros\App\Middleware\AuthenticateAdmin;

Route::middleware(AuthenticateAdmin::class)->prefix('toolbox')->group(function() {
    Route::livewire('/', 'meros::toolbox.form-builder')->name('meros.toolbox.form-builder');
});