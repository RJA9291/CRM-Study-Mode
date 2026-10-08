<?php

namespace App\Services\Messaging;

interface MessagingChannel
{
    public function name(): string;

    public function send(string $recipient, string $text): void;
}
