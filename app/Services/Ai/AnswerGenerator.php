<?php

namespace App\Services\Ai;

interface AnswerGenerator
{
    /**
     * @throws AnswerGenerationException
     */
    public function generate(string $system, string $userMessage): string;
}
