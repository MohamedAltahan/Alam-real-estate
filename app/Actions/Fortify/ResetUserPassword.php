<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        // الممنوع من تغيير كلمة المرور لا يلتف على المنع بـ«نسيت كلمة المرور»
        if (! $user->can(User::CHANGE_PASSWORD)) {
            throw ValidationException::withMessages(['password' => 'لا تملك صلاحية تغيير كلمة المرور — تواصل مع مدير النظام.']);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        app(ActivityLogger::class)->passwordChanged($user, 'عبر «نسيت كلمة المرور»');
    }
}
