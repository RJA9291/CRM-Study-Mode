<?php

namespace App\Services\Knowledge;

interface DriveClient
{
    /**
     * Lists every non-folder file under the folder, descending into subfolders.
     *
     * @return iterable<DriveFile>
     */
    public function listFiles(string $folderId): iterable;

    /**
     * Returns the plain-text content of the file, or null when its type is not supported.
     */
    public function fetchText(DriveFile $file): ?string;
}
