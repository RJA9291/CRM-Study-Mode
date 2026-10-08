<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Services\Knowledge\DriveClient;
use App\Services\Knowledge\TextChunker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SyncKnowledgeSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public KnowledgeSource $source) {}

    public function uniqueId(): string
    {
        return (string) $this->source->getKey();
    }

    public function handle(DriveClient $drive, TextChunker $chunker): void
    {
        $source = $this->source;
        $source->forceFill(['sync_status' => 'syncing', 'sync_error' => null])->save();

        try {
            $seen = [];
            $skipped = 0;

            foreach ($drive->listFiles($source->drive_folder_id) as $file) {
                $seen[] = $file->id;

                $document = KnowledgeDocument::firstOrNew([
                    'knowledge_source_id' => $source->id,
                    'drive_file_id' => $file->id,
                ]);

                $unchanged = $document->exists
                    && $file->modifiedTime
                    && $document->modified_time?->equalTo($file->modifiedTime);

                $document->fill([
                    'title' => $file->name,
                    'mime_type' => $file->mimeType,
                    'web_link' => $file->webLink,
                ]);

                if ($unchanged) {
                    $document->save();

                    continue;
                }

                try {
                    $text = $drive->fetchText($file);
                } catch (Throwable $e) {
                    Log::warning('Knowledge file could not be read', ['file' => $file->name, 'error' => $e->getMessage()]);
                    $text = null;
                }

                if ($text === null) {
                    $skipped++;
                }

                DB::transaction(function () use ($document, $file, $text, $chunker) {
                    $document->modified_time = $file->modifiedTime;
                    $document->save();
                    $document->chunks()->delete();

                    foreach ($chunker->chunk((string) $text) as $i => $content) {
                        $document->chunks()->create(['position' => $i, 'content' => Str::limit($content, 60000, '')]);
                    }
                });
            }

            $source->documents()->whereNotIn('drive_file_id', $seen)->delete();

            $source->forceFill([
                'sync_status' => 'ok',
                'sync_error' => $skipped > 0 ? "{$skipped} fail tidak disokong atau tidak dapat dibaca." : null,
                'last_synced_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $source->forceFill(['sync_status' => 'failed', 'sync_error' => Str::limit($e->getMessage(), 1000)])->save();

            throw $e;
        }
    }
}
