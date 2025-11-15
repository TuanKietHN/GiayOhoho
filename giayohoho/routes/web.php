<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Payment\SepayController;
use App\Http\Controllers\Payment\SepayCheckoutController;

Route::get('/', function () {
    return view('app');
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*$');

Route::post('/sepay/ipn', [SepayController::class, 'ipn']);
Route::get('/sepay/checkout', [SepayCheckoutController::class, 'checkout']);
