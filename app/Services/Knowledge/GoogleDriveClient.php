<?php

namespace App\Services\Knowledge;

use Carbon\CarbonImmutable;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class GoogleDriveClient implements DriveClient
{
    private const FOLDER = 'application/vnd.google-apps.folder';

    private const EXPORTS = [
        'application/vnd.google-apps.document' => 'text/plain',
        'application/vnd.google-apps.presentation' => 'text/plain',
        'application/vnd.google-apps.spreadsheet' => 'text/csv',
    ];

    private const DOWNLOADABLE_TEXT = ['text/plain', 'text/markdown', 'text/csv', 'application/json'];

    private const PDF = 'application/pdf';

    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private ?Drive $drive = null;

    public function __construct(
        private readonly string $credentialsPath,
        private readonly int $maxFileBytes,
    ) {}

    public function listFiles(string $folderId): iterable
    {
        $pending = [$folderId];
        $seen = [];

        while ($pending !== []) {
            $current = array_shift($pending);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $pageToken = null;

            do {
                $result = $this->drive()->files->listFiles([
                    'q' => sprintf("'%s' in parents and trashed = false", addslashes($current)),
                    'fields' => 'nextPageToken, files(id, name, mimeType, modifiedTime, webViewLink, size)',
                    'pageSize' => 200,
                    'pageToken' => $pageToken,
                    'supportsAllDrives' => true,
                    'includeItemsFromAllDrives' => true,
                ]);

                foreach ($result->getFiles() as $file) {
                    if ($file->getMimeType() === self::FOLDER) {
                        $pending[] = $file->getId();

                        continue;
                    }

                    yield new DriveFile(
                        id: $file->getId(),
                        name: $file->getName(),
                        mimeType: $file->getMimeType(),
                        modifiedTime: $file->getModifiedTime() ? CarbonImmutable::parse($file->getModifiedTime()) : null,
                        webLink: $file->getWebViewLink(),
                        size: $file->getSize() !== null ? (int) $file->getSize() : null,
                    );
                }

                $pageToken = $result->getNextPageToken();
            } while ($pageToken);
        }
    }

    public function fetchText(DriveFile $file): ?string
    {
        if (isset(self::EXPORTS[$file->mimeType])) {
            $response = $this->drive()->files->export($file->id, self::EXPORTS[$file->mimeType], ['alt' => 'media']);

            return (string) $response->getBody();
        }

        $supported = in_array($file->mimeType, self::DOWNLOADABLE_TEXT, true)
            || $file->mimeType === self::PDF
            || $file->mimeType === self::DOCX;

        if (! $supported || ($file->size !== null && $file->size > $this->maxFileBytes)) {
            return null;
        }

        $bytes = (string) $this->drive()->files->get($file->id, ['alt' => 'media', 'supportsAllDrives' => true])->getBody();

        return match ($file->mimeType) {
            self::PDF => (new PdfParser)->parseContent($bytes)->getText(),
            self::DOCX => $this->docxToText($bytes),
            default => $bytes,
        };
    }

    private function docxToText(string $bytes): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bytes);

        try {
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                return '';
            }
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
        } finally {
            @unlink($tmp);
        }

        $xml = preg_replace('~</w:p>~', "\n\n", $xml) ?? $xml;
        $xml = preg_replace('~<w:tab/>~', "\t", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function drive(): Drive
    {
        if ($this->drive) {
            return $this->drive;
        }

        $path = $this->credentialsPath;
        if (! str_starts_with($path, '/') && ! preg_match('~^[A-Za-z]:[\\\\/]~', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            throw new RuntimeException("Google service account key not found at [{$this->credentialsPath}].");
        }

        $client = new GoogleClient;
        $client->setAuthConfig($path);
        $client->setScopes([Drive::DRIVE_READONLY]);

        return $this->drive = new Drive($client);
    }
}
