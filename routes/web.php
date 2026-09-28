<?php

use App\Domain\Access\HomeRoute;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect(HomeRoute::for(auth()->user()))
    : redirect()->route('login'));
