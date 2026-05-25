<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;

Route::post('/save-user', [UserController::class, 'saveUser']);
Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', [AdminController::class, 'page']);
