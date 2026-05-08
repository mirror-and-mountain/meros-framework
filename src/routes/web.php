<?php 

use Illuminate\Support\Facades\Route;
use MM\Meros\App\Middleware\AuthenticateAdmin;

Route::livewire('/test', 'meros::test')->name('livewire.test')->middleware(AuthenticateAdmin::class);