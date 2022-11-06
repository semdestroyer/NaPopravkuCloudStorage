<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/user-profile', [AuthController::class, 'userProfile']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'file'
], function ($router) {
    Route::post('/upload', [FileController::class, 'upload']);
    Route::get('/download', [FileController::class, 'download']);
    Route::POST('/rename', [FileController::class, 'rename']);
    Route::post('/create/directory', [FileController::class, 'createDirectory']);
    Route::post('/delete/file', [FileController::class, 'delete']);
    Route::get('/user/size/directory', [FileController::class, 'getUserFilesSizeInsideDirectory']);
    Route::get('/user/size', [FileController::class, 'getUserFilesSize']);
    Route::get('/user/files', [FileController::class, 'showUserFiles']);
    Route::post('/generate/url', [FileController::class, 'generateFilePublicLink']);
    Route::get('/{id}', [FileController::class, 'getPublicFile']);
});
