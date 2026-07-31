@extends('layouts.guest')

@section('title', 'استعادة كلمة المرور')

@section('content')
<div class="min-h-screen relative flex items-center justify-center p-4 font-sans overflow-hidden"
     style="background: radial-gradient(130% 130% at 50% 0%, #1b1a4a 0%, #111033 52%, #0b0a24 100%);">
    <div class="relative w-full max-w-md bg-white rounded-card shadow-2xl p-8 sm:p-10">
        <p class="text-accent-600 font-semibold text-sm mb-2">لوحة الإدارة</p>
        <h1 class="text-2xl font-bold text-primary-900 mb-1.5">استعادة كلمة المرور</h1>
        <p class="text-gray-500 text-sm mb-7">اكتب بريدك وهنبعتلك رابط لإعادة تعيين كلمة المرور.</p>

        @if (session('status'))
            <div class="mb-5 rounded-field bg-success-soft text-success text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-field border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-ink
                              focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white
                              @error('email') border-danger @enderror">
                @error('email')
                    <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold py-3 transition">
                إرسال رابط الاستعادة
            </button>

            <a href="{{ route('login') }}" class="block text-center text-sm text-gray-500 hover:text-primary-700">
                العودة لتسجيل الدخول
            </a>
        </form>
    </div>
</div>
@endsection
