<?php

namespace App\Filament\Resources\KnowledgeSources\Pages;

use App\Filament\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Jobs\SyncKnowledgeSource;
use App\Models\KnowledgeSource;
use App\Services\Knowledge\KnowledgeAnswerService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ManageKnowledgeSources extends ManageRecords
{
    protected static string $resource = KnowledgeSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ask')
                ->label('Uji soalan')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')
                ->modalSubmitActionLabel('Tanya')
                ->schema([
                    Textarea::make('question')->label('Soalan')->required()->rows(3),
                ])
                ->action(function (array $data, KnowledgeAnswerService $knowledge) {
                    Notification::make()
                        ->title('Jawapan')
                        ->body(new HtmlString(nl2br(e($knowledge->answer($data['question'])))))
                        ->persistent()
                        ->send();
                }),
            CreateAction::make()->after(fn (KnowledgeSource $record) => SyncKnowledgeSource::dispatch($record)),
        ];
    }
}
