<?php

use App\Enums\UserRole;
use App\Jobs\SyncKnowledgeSource;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Services\Messaging\TelegramChannel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('telegram:set-webhook {--url= : Override the public webhook URL}', function (TelegramChannel $telegram) {
    $secret = (string) config('crm.telegram.webhook_secret');
    if ($secret === '') {
        $this->error('Set TELEGRAM_WEBHOOK_SECRET in .env first.');

        return 1;
    }

    $url = $this->option('url') ?: route('telegram.webhook');
    $result = $telegram->setWebhook($url, $secret);
    $bot = $telegram->getMe();

    $this->info("Webhook set to {$url} for @".($bot['username'] ?? '?').': '.($result['description'] ?? 'ok'));
})->purpose('Register the Telegram bot webhook');

Artisan::command('knowledge:sync {source? : Knowledge source ID; all active sources when omitted}', function () {
    $sources = KnowledgeSource::query()
        ->when($this->argument('source'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('is_active', true))
        ->get();

    foreach ($sources as $source) {
        SyncKnowledgeSource::dispatch($source);
        $this->line("Queued sync for [{$source->id}] {$source->name}");
    }
})->purpose('Queue a Google Drive sync for knowledge sources');

Artisan::command('crm:make-admin {email : Email of an existing registered account}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('No account with that email. Register at /app/register first.');

        return 1;
    }

    $user->forceFill(['role' => UserRole::SuperAdmin])->save();
    $user->approve();

    $this->info("{$user->email} is now an approved super admin.");
})->purpose('Promote a registered account to approved super admin');

Schedule::command('knowledge:sync')->dailyAt('03:00');
