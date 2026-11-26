<?php

use Illuminate\Support\Facades\Route;

// The API has no public front page: say what this is and nothing about the framework or its version.
Route::get('/', fn () => response()->json(['service' => 'ArmorHub API']));
