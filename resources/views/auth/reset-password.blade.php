@extends('layouts.guest')

@section('title', 'تعيين كلمة مرور جديدة')

@section('content')
<div class="min-h-screen relative flex items-center justify-center p-4 font-sans overflow-hidden"
     style="background: radial-gradient(130% 130% at 50% 0%, #1b1a4a 0%, #111033 52%, #0b0a24 100%);">
    <div class="relative w-full max-w-md bg-white rounded-card shadow-2xl p-8 sm:p-10">
        <p class="text-accent-600 font-semibold text-sm mb-2">لوحة الإدارة</p>
        <h1 class="text-2xl font-bold text-primary-900 mb-1.5">تعيين كلمة مرور جديدة</h1>
        <p class="text-gray-500 text-sm mb-7">اختر كلمة مرور جديدة لحسابك.</p>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus
                       class="w-full rounded-field border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-ink
                              focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white
                              @error('email') border-danger @enderror">
                @error('email')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">كلمة المرور الجديدة</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="w-full rounded-field border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-ink
                              focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white
                              @error('password') border-danger @enderror">
                @error('password')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">تأكيد كلمة المرور</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                       class="w-full rounded-field border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-ink
                              focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:bg-white">
            </div>

            <button type="submit"
                    class="w-full rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold py-3 transition">
                حفظ كلمة المرور
            </button>
        </form>
    </div>
</div>
@endsection
