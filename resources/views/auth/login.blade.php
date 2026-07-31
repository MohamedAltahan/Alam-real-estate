@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
<div class="min-h-screen relative flex items-center justify-center p-4 font-sans overflow-hidden"
     style="background: radial-gradient(130% 130% at 50% 0%, #1b1a4a 0%, #111033 52%, #0b0a24 100%);">

    {{-- توهّج خفيف يوحي بغروب مدينة الكويت (بديل مؤقت للصورة الحقيقية) --}}
    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2"
         style="background: radial-gradient(60% 80% at 50% 100%, rgba(251,211,0,.10) 0%, transparent 70%);"></div>

    <div class="relative w-full max-w-md bg-white rounded-card shadow-2xl p-8 sm:p-10">
        <p class="text-accent-600 font-semibold text-sm mb-2">لوحة الإدارة</p>
        <h1 class="text-2xl font-bold text-primary-900 mb-1.5">مرحباً بعودتك 👋</h1>
        <p class="text-gray-500 text-sm mb-7">قم بتسجيل الدخول إلى لوحة تحكم علم العقارية</p>

        @if (session('status'))
            <div class="mb-5 rounded-field bg-success-soft text-success text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" placeholder="admin@alam.com"
                       class="w-full rounded-field border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-ink
                              placeholder:text-gray-400 transition
                              focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white
                              @error('email') border-danger focus:border-danger focus:ring-danger/20 @enderror">
                @error('email')
                    <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">كلمة المرور</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full rounded-field border border-gray-200 bg-gray-50 ps-4 pe-11 py-3 text-sm text-ink
                                  placeholder:text-gray-400 transition
                                  focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white">
                    <button type="button" onclick="togglePw()" tabindex="-1"
                            class="absolute inset-y-0 end-3 flex items-center text-gray-400 hover:text-gray-600"
                            aria-label="إظهار كلمة المرور">
                        <svg id="eye" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" name="remember"
                           class="rounded border-gray-300 text-primary-900 focus:ring-primary-500/30">
                    تذكّرني
                </label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-600 hover:text-primary-800">
                    نسيت كلمة المرور؟
                </a>
            </div>

            <button type="submit"
                    class="w-full rounded-field bg-primary-900 hover:bg-primary-800 active:bg-primary-950
                           text-white font-semibold py-3 transition
                           focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:ring-offset-2">
                تسجيل الدخول
            </button>
        </form>
    </div>
</div>

<script>
    function togglePw() {
        var i = document.getElementById('password');
        i.type = i.type === 'password' ? 'text' : 'password';
    }
</script>
@endsection
