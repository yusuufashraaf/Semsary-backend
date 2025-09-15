<?php

use Illuminate\Support\Facades\Route;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use App\Models\Notification;
use App\Models\Purchase;
use App\Models\Booking;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/user/{id}/reviews',function( int $id){
    return Review::with('property','customer')->where('user_id',$id)->get();
});

Route::get('/user/{id}/reviews',function(int $id){
    return Review::with('property','customer')->where('user_id',$id)->get();
});

Route::get('/user/{id}/properties',function(int $id){
    return Property::where('owner_id',$id)->get();
});

Route::get('/user/{id}/notifications',function(int $id){
    return Notification::where('user_id',$id)->get();
});

Route::get('/user/{id}/purchases',function(int $id){
    return Purchase::with('property')->where('user_id',$id)->get();
});

Route::get('/user/{id}/bookings',function(int $id){
    return Booking::with('property')->where('user_id',$id)->get();
});


Route::get('/users',function(){
    return User::get();
});

Route::get('/users/{id}',function(int $id){
    return User::with('properties','reviews','notifications')->find($id);
});

Route::get('/reviews',function(){
    return Review::with('property','customer')->get();
});

Route::get('/reviews/{id}',function(int $id){
    return Review::with('property','customer')->find($id);
});

Route::get('/properties',function(){
    return Property::get();
});

Route::get('/properties/{id}',function(int $id){
    return Property::with('owner','reviews')->find($id);
});

Route::get('/notifications',function(){
    return Notification::get();
});

Route::get('/notifications/{id}',function(int $id){
    return Notification::find($id);
});

