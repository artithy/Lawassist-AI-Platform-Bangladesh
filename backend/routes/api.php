<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'LawAssist AI Backend is running!',
        'status' => 'healthy'
    ]);
});
