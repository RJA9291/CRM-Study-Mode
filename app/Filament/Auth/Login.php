<?php

namespace App\Filament\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Tells correctly-authenticated but unapproved students why they cannot sign in yet.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $email = (string) ($this->data['email'] ?? '');
        $password = (string) ($this->data['password'] ?? '');

        $user = $email !== '' ? User::where('email', $email)->first() : null;

        if ($user && $user->status !== UserStatus::Approved && Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'data.email' => $user->status === UserStatus::Rejected
                    ? 'Pendaftaran anda telah ditolak. Sila hubungi admin.'
                    : 'Akaun anda masih menunggu kelulusan Super Admin.',
            ]);
        }

        return parent::authenticate();
    }
}
