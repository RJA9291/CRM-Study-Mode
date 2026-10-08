<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * New students register as "pending"; they are not logged in until a super admin approves them.
 */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent()->label('Nama penuh'),
            $this->getEmailFormComponent(),
            TextInput::make('student_id')->label('No. matrik / ID pelajar')->required()->maxLength(50),
            TextInput::make('phone')->label('No. telefon')->tel()->maxLength(30),
            TextInput::make('programme')->label('Program / kursus')->maxLength(150),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $user = $this->getUserModel()::make($data);
        $user->forceFill(['role' => UserRole::Student, 'status' => UserStatus::Pending])->save();

        return $user;
    }

    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if (Filament::auth()->check()) {
            Filament::auth()->logout();
        }

        $user = $this->wrapInDatabaseTransaction(function (): Model {
            $data = $this->form->getState();

            return $this->handleRegistration($data);
        });

        event(new Registered($user));

        Notification::make()
            ->title('Pendaftaran diterima')
            ->body('Akaun anda sedang menunggu kelulusan Super Admin. Anda boleh log masuk selepas diluluskan.')
            ->success()
            ->persistent()
            ->send();

        $this->redirect(Filament::getLoginUrl());

        return null;
    }
}
