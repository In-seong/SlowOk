<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 정적 문서 서빙 (SPA catch-all 우회)
Route::get('/docs/{file}', function (string $file) {
    $path = public_path("docs/{$file}");
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'text/html; charset=utf-8']);
})->where('file', '.*\.html');
