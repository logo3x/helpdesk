<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AslController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && $user->asl_accepted_at !== null) {
            return redirect()->intended('/');
        }

        return view('asl.accept');
    }

    public function accept(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->asl_accepted_at === null) {
            $user->forceFill(['asl_accepted_at' => now()])->save();
        }

        return redirect()->intended('/');
    }
}
