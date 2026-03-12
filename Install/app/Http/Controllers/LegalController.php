<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register') && \App\Models\SystemSetting::get('enable_registration', true),
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/Terms', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register') && \App\Models\SystemSetting::get('enable_registration', true),
        ]);
    }

    public function cookies(): Response
    {
        return Inertia::render('Legal/Cookies', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register') && \App\Models\SystemSetting::get('enable_registration', true),
        ]);
    }
}
