<?php

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'LawAssist AI Backend is running!',
        'status' => 'healthy'
    ]);
});

// The signed-in user (verified Supabase token). 401 without a valid token.
Route::middleware('auth:supabase')->get('/me', MeController::class);
