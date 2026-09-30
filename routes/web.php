<?php

use App\Livewire\Home;
use App\Livewire\Import\ImportIndex;
use App\Livewire\Import\ImportShow;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name('home');
Route::livewire('/imports', ImportIndex::class)->name('imports.index');
Route::livewire('/imports/{import}', ImportShow::class)->name('imports.show');
