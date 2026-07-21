<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __invoke(Request $request, string $currency): RedirectResponse
    {
        $supportedCurrencies = config('currency.currencies', []);

        abort_unless(array_key_exists(strtoupper($currency), $supportedCurrencies), 404);

        $request->session()->put('active_currency', strtoupper($currency));

        return redirect()->back();
    }
}
