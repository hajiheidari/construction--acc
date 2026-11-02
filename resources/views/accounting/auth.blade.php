@extends('accounting.layout')
@section('content')
<div class="login-card card"><span class="eyebrow">خوش آمدید</span><h1>ورود به حساب ساختمان</h1><p class="muted">برای دسترسی به حساب‌های پروژه وارد شوید.</p><form method="post" action="/login" class="stack">@csrf<label>ایمیل<input name="email" type="email" required autocomplete="username" value="{{ old('email') }}" dir="ltr"></label><label>رمز عبور<input name="password" type="password" required autocomplete="current-password"></label><button>ورود به فضای کاری ←</button></form><p class="muted">حساب کاربری را از مدیر پروژه دریافت کنید.</p></div>
@endsection
