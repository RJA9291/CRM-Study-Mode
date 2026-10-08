<?php

namespace App\Services\Knowledge;

use Carbon\CarbonImmutable;

final readonly class DriveFile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mimeType,
        public ?CarbonImmutable $modifiedTime = null,
        public ?string $webLink = null,
        public ?int $size = null,
    ) {}
}
