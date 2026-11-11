<?php

use Illuminate\Support\Facades\Route;

// CRM interno: no hay página pública. La raíz lleva al panel (o al login si no hay sesión).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
