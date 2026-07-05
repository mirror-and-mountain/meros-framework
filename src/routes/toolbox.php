<?php 

use MM\Meros\Facades\Framework;
use Illuminate\Support\Facades\Route;
use MM\Meros\App\Middleware\AuthenticateAdmin;

Route::middleware(AuthenticateAdmin::class)->prefix('toolbox')->group(function() {
    Route::livewire('/', 'toolbox::index')->name('meros.toolbox.index');

    if (Framework::featureEnabled('forms')) {
        Route::livewire('/form-builder', 'toolbox::forms.builder')->name('meros.toolbox.form-builder');
        Route::livewire('/form-builder/{formID?}', 'toolbox::forms.builder')->name('meros.toolbox.form-builder.edit');
    }
});