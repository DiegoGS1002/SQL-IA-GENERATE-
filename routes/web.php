<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FirebirdConnectionController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'desktop')->name('desktop');

Route::prefix('api/v1')->group(function (): void {
    Route::get('connections', [FirebirdConnectionController::class, 'index']);
    Route::post('connections', [FirebirdConnectionController::class, 'store']);
    Route::post('connections/{connection}/test', [FirebirdConnectionController::class, 'test']);
    Route::post('connections/{connection}/sync', [FirebirdConnectionController::class, 'synchronize']);
    Route::get('connections/{connection}/schema', [FirebirdConnectionController::class, 'schema']);
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::patch('conversations/{conversation}', [ConversationController::class, 'update']);
    Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store']);
});
