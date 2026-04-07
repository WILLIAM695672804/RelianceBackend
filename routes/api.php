<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });


Route::prefix('v1')->group(function () {
    require base_path('app/Modules/Auth/Routes/api.php');
    require base_path('app/Modules/Profile/Routes/api.php');
    require base_path('app/Modules/Schedule/Routes/api.php');
    require base_path('app/Modules/Matching/Routes/api.php');
    require base_path('app/Modules/Gamification/Routes/api.php');
});