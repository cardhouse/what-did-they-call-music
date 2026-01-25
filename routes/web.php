<?php

use App\Http\Controllers\ImageProxyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
})->name('home');

Route::get('/img/{url}', [ImageProxyController::class, 'proxy'])
    ->where('url', '.*')
    ->name('image.proxy');
