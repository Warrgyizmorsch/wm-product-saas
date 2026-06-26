<?php

use Illuminate\Support\Facades\Route;

Route::prefix('production')
    ->as('production.')
    ->group(function (): void {
        //
    });
