<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class AuthController
{
    public function form()
    {
        return view('accounting.auth');
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', mb_strtolower($d['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'تلاش بیش از حد؛ یک دقیقه بعد دوباره امتحان کنید.']);
        }
        if (! Auth::attempt($d)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'ایمیل یا رمز عبور صحیح نیست.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return $r->expectsJson() ? response()->json(['user' => Auth::user()]) : redirect('/');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}
