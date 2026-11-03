<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\TagController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::get('posts/trashed', [PostController::class, 'trashed'])->middleware('auth:sanctum');
Route::apiResource('posts', PostController::class)->only(['index', 'show']);
Route::get('posts/{post}/comments', [CommentController::class, 'index']);

Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::get('tags', [TagController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('user', function (Request $request) {
        return $request->user();
    });

    Route::post('posts/{post}/restore', [PostController::class, 'restore'])->withTrashed();
    Route::apiResource('posts', PostController::class)->except(['index', 'show']);
    Route::post('posts/{post}/publish', [PostController::class, 'publish']);
    Route::post('posts/{post}/unpublish', [PostController::class, 'unpublish']);
    Route::post('posts/{post}/comments', [CommentController::class, 'store']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('tags', [TagController::class, 'store']);
    Route::put('posts/{post}/tags', [TagController::class, 'syncTags']);
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
});
