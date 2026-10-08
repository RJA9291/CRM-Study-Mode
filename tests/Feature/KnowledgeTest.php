<?php

namespace Tests\Feature;

use App\Jobs\SyncKnowledgeSource;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Services\Ai\AnswerGenerationException;
use App\Services\Ai\AnswerGenerator;
use App\Services\Knowledge\DriveClient;
use App\Services\Knowledge\DriveFile;
use App\Services\Knowledge\KnowledgeAnswerService;
use App\Services\Knowledge\TextChunker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private FakeAnswerGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new FakeAnswerGenerator;
        $this->app->instance(AnswerGenerator::class, $this->generator);
    }

    private function fakeDrive(array $files): FakeDriveClient
    {
        $drive = new FakeDriveClient($files);
        $this->app->instance(DriveClient::class, $drive);

        return $drive;
    }

    public function test_folder_id_is_extracted_from_drive_links(): void
    {
        $this->assertSame('1AbC_dEf-123', KnowledgeSource::extractFolderId('https://drive.google.com/drive/folders/1AbC_dEf-123?usp=sharing'));
        $this->assertSame('1AbC_dEf-123', KnowledgeSource::extractFolderId('https://drive.google.com/open?id=1AbC_dEf-123'));
        $this->assertNull(KnowledgeSource::extractFolderId('https://example.com/nope'));
    }

    public function test_chunker_splits_long_text_with_bounded_chunk_size(): void
    {
        $text = implode("\n\n", array_fill(0, 40, str_repeat('Ayat ujian yang panjang. ', 12)));
        $chunks = (new TextChunker(1500, 200))->chunk($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1500, mb_strlen($chunk));
        }
        $this->assertSame([], (new TextChunker)->chunk("  \n\n "));
    }

    public function test_sync_indexes_files_skips_unchanged_and_removes_deleted(): void
    {
        $source = KnowledgeSource::create(['name' => 'Nota', 'drive_url' => 'https://drive.google.com/drive/folders/FOLDER123456']);
        $t1 = CarbonImmutable::parse('2026-10-01 10:00:00');

        $drive = $this->fakeDrive([
            [new DriveFile('f1', 'Jadual Peperiksaan', 'application/vnd.google-apps.document', $t1), 'Peperiksaan akhir Fizik pada 12 Disember di Dewan A.'],
            [new DriveFile('f2', 'Gambar.png', 'image/png', $t1), null],
        ]);

        SyncKnowledgeSource::dispatchSync($source);

        $source->refresh();
        $this->assertSame('ok', $source->sync_status);
        $this->assertSame(2, $source->documents()->count());
        $this->assertSame(1, $source->chunks()->count());
        $this->assertSame(2, $drive->fetches);

        // Second run: f1 unchanged (not re-fetched), f2 gone from Drive.
        $drive->files = [$drive->files[0]];
        SyncKnowledgeSource::dispatchSync($source);

        $this->assertSame(2, $drive->fetches);
        $this->assertSame(['f1'], KnowledgeDocument::pluck('drive_file_id')->all());
    }

    public function test_sync_failure_is_recorded_on_the_source(): void
    {
        $source = KnowledgeSource::create(['name' => 'Nota', 'drive_url' => 'https://drive.google.com/drive/folders/FOLDER123456']);
        $drive = $this->fakeDrive([]);
        $drive->failWith = 'Permission denied';

        try {
            SyncKnowledgeSource::dispatchSync($source);
        } catch (\RuntimeException) {
        }

        $this->assertSame('failed', $source->fresh()->sync_status);
        $this->assertStringContainsString('Permission denied', $source->fresh()->sync_error);
    }

    public function test_answers_use_matching_chunks_from_active_sources_only(): void
    {
        $active = KnowledgeSource::create(['name' => 'Aktif', 'drive_url' => 'FOLDERACTIVE123']);
        $inactive = KnowledgeSource::create(['name' => 'Lama', 'drive_url' => 'FOLDEROLD12345', 'is_active' => false]);

        $active->documents()->create(['drive_file_id' => 'a', 'title' => 'Jadual Peperiksaan'])
            ->chunks()->create(['position' => 0, 'content' => 'Peperiksaan Fizik pada 12 Disember di Dewan A.']);
        $inactive->documents()->create(['drive_file_id' => 'b', 'title' => 'Jadual Lama'])
            ->chunks()->create(['position' => 0, 'content' => 'Peperiksaan Fizik pada 1 Jun (dibatalkan).']);

        $this->generator->reply = 'Peperiksaan Fizik pada 12 Disember. Sumber: Jadual Peperiksaan';

        $answer = app(KnowledgeAnswerService::class)->answer('Bila peperiksaan fizik?');

        $this->assertSame('Peperiksaan Fizik pada 12 Disember. Sumber: Jadual Peperiksaan', $answer);
        $this->assertStringContainsString('12 Disember', $this->generator->lastUserMessage);
        $this->assertStringNotContainsString('1 Jun', $this->generator->lastUserMessage);
    }

    public function test_no_match_does_not_call_the_model(): void
    {
        $answer = app(KnowledgeAnswerService::class)->answer('Siapa pensyarah kimia?');

        $this->assertSame(KnowledgeAnswerService::NO_MATCH, $answer);
        $this->assertNull($this->generator->lastUserMessage);
    }

    public function test_model_failure_returns_a_friendly_message(): void
    {
        $source = KnowledgeSource::create(['name' => 'Aktif', 'drive_url' => 'FOLDERACTIVE123']);
        $source->documents()->create(['drive_file_id' => 'a', 'title' => 'Doc'])
            ->chunks()->create(['position' => 0, 'content' => 'Yuran semester RM500.']);
        $this->generator->fail = true;

        $this->assertSame(KnowledgeAnswerService::UNAVAILABLE, app(KnowledgeAnswerService::class)->answer('Berapa yuran semester?'));
    }
}

class FakeAnswerGenerator implements AnswerGenerator
{
    public string $reply = 'ok';

    public bool $fail = false;

    public ?string $lastUserMessage = null;

    public function generate(string $system, string $userMessage): string
    {
        $this->lastUserMessage = $userMessage;

        if ($this->fail) {
            throw new AnswerGenerationException('boom');
        }

        return $this->reply;
    }
}

class FakeDriveClient implements DriveClient
{
    public int $fetches = 0;

    public ?string $failWith = null;

    /** @param list<array{0: DriveFile, 1: ?string}> $files */
    public function __construct(public array $files) {}

    public function listFiles(string $folderId): iterable
    {
        if ($this->failWith) {
            throw new \RuntimeException($this->failWith);
        }

        foreach ($this->files as [$file]) {
            yield $file;
        }
    }

    public function fetchText(DriveFile $file): ?string
    {
        $this->fetches++;

        foreach ($this->files as [$candidate, $text]) {
            if ($candidate->id === $file->id) {
                return $text;
            }
        }

        return null;
    }
}
