<?php

use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name('home');
