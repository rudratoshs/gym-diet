<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;

Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy.policy');
Route::get('/terms-of-service', [PageController::class, 'termsOfService'])->name('terms.service');
Route::get('/delete-my-data', [PageController::class, 'deleteMyData'])->name('delete.data');
Route::get('/', function () {
    return view('welcome');
});
