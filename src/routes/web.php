<?php 

use Illuminate\Support\Facades\Route;

Route::get('/portal', function () {
    return 'hello world';
})->name('meros.portal');