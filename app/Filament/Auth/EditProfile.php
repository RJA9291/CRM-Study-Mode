<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public static function isSimple(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('avatar')
                ->label('Gambar profil')
                ->image()
                ->avatar()
                ->disk('public')
                ->directory('avatars')
                ->maxSize(2048),
            $this->getNameFormComponent()->label('Nama penuh'),
            $this->getEmailFormComponent(),
            TextInput::make('student_id')->label('No. matrik / ID pelajar')->maxLength(50),
            TextInput::make('phone')->label('No. telefon')->tel()->maxLength(30),
            TextInput::make('programme')->label('Program / kursus')->maxLength(150),
            TextInput::make('semester')->numeric()->minValue(1)->maxValue(20),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
