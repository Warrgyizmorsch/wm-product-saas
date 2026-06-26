<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeadController;

Route::get('/', function () {
    return view('dashboard');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/crm/leads/create', [LeadController::class, 'create'])->name('crm.leads.create');
Route::get('/crm/leads', [LeadController::class, 'index'])->name('crm.leads.index');
Route::post('/crm/leads', [LeadController::class, 'store'])->name('crm.leads.store');
Route::get('/crm/leads/{lead}/edit', [LeadController::class, 'edit'])->name('crm.leads.edit');
Route::put('/crm/leads/{lead}', [LeadController::class, 'update'])->name('crm.leads.update');
Route::delete('/crm/leads/{lead}', [LeadController::class, 'destroy'])->name('crm.leads.destroy');




