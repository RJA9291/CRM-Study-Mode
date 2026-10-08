<?php

namespace App\Providers;

use App\Services\Ai\AnswerGenerator;
use App\Services\Ai\ClaudeAnswerGenerator;
use App\Services\Knowledge\DriveClient;
use App\Services\Knowledge\GoogleDriveClient;
use App\Services\Knowledge\TextChunker;
use App\Services\Messaging\TelegramChannel;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnswerGenerator::class, fn () => new ClaudeAnswerGenerator(
            apiKey: config('crm.anthropic.api_key'),
            model: config('crm.anthropic.model'),
            effort: config('crm.anthropic.effort'),
            maxTokens: config('crm.anthropic.max_tokens'),
        ));

        $this->app->bind(DriveClient::class, fn () => new GoogleDriveClient(
            credentialsPath: config('crm.google.service_account_json'),
            maxFileBytes: config('crm.knowledge.max_file_bytes'),
        ));

        $this->app->bind(TextChunker::class, fn () => new TextChunker(
            size: config('crm.knowledge.chunk_size'),
            overlap: config('crm.knowledge.chunk_overlap'),
        ));

        $this->app->bind(TelegramChannel::class, fn () => new TelegramChannel(config('crm.telegram.bot_token')));
    }

    public function boot(): void
    {
        //
    }
}
