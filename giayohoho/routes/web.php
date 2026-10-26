<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::get('/login', function () {
    return view('app');
})->name('login');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
