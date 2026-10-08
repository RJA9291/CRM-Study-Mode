<?php

namespace App\Filament\App\Resources\Credentials\Pages;

use App\Filament\App\Resources\Credentials\CredentialResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageCredentials extends ManageRecords
{
    protected static string $resource = CredentialResource::class;

    public function getSubheading(): ?string
    {
        return CredentialResource::isUnlocked()
            ? 'Locker terbuka. Ia akan dikunci semula secara automatik selepas 10 minit.'
            : 'Semua password disulitkan. Masukkan password akaun CRM anda untuk lihat atau salin.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unlock')
                ->label('Buka kunci locker')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('warning')
                ->visible(fn () => ! CredentialResource::isUnlocked())
                ->modalHeading('Sahkan identiti')
                ->modalSubmitActionLabel('Buka kunci')
                ->schema([
                    TextInput::make('current_password')
                        ->label('Password akaun CRM')
                        ->password()
                        ->required()
                        ->currentPassword(),
                ])
                ->action(function () {
                    session([CredentialResource::UNLOCK_SESSION_KEY => time()]);
                    Notification::make()->title('Locker dibuka')->success()->send();
                }),
            Action::make('lock')
                ->label('Kunci')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('gray')
                ->visible(fn () => CredentialResource::isUnlocked())
                ->action(fn () => session()->forget(CredentialResource::UNLOCK_SESSION_KEY)),
            CreateAction::make()->mutateDataUsing(fn (array $data) => [...$data, 'user_id' => auth()->id()]),
        ];
    }
}
