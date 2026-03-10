<?php

use App\Models\UserNotification;
use Illuminate\Support\Facades\Route;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use App\Models\Purchase;
use App\Models\Booking;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/users', function () {
    return User::get();
});
Route::get('/reviews', function () {
    return Review::with('property', 'customer')->get();
});

Route::get('/reviews/{id}', function (int $id) {
    return Review::with('property', 'customer')->find($id);
});

Route::get('/properties', function () {
    return Property::get();
});

Route::get('/properties/{id}', function (int $id) {
    return Property::with('owner', 'reviews')->find($id);
});

Route::get('/notifications', function () {
    return UserNotification::get();
});

Route::get('/notifications/{id}', function (int $id) {
    return UserNotification::find($id);
});