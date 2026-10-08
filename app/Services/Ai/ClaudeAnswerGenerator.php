<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Illuminate\Support\Facades\Log;

class ClaudeAnswerGenerator implements AnswerGenerator
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $effort,
        private readonly int $maxTokens,
    ) {}

    public function generate(string $system, string $userMessage): string
    {
        if (blank($this->apiKey)) {
            throw new AnswerGenerationException('ANTHROPIC_API_KEY is not configured.');
        }

        $client = new Client(apiKey: $this->apiKey);

        try {
            // "default" fallbacks reroute policy refusals to a suitable model server-side.
            $message = $client->beta->messages->create(
                maxTokens: $this->maxTokens,
                messages: [['role' => 'user', 'content' => $userMessage]],
                model: $this->model,
                fallbacks: 'default',
                outputConfig: ['effort' => $this->effort],
                system: $system,
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIStatusException $e) {
            Log::warning('Claude API returned an error status', ['status' => $e->status ?? null, 'message' => $e->getMessage()]);
            throw new AnswerGenerationException('Claude API error: '.$e->getMessage(), previous: $e);
        } catch (APIConnectionException $e) {
            throw new AnswerGenerationException('Could not reach the Claude API.', previous: $e);
        } catch (APIException $e) {
            throw new AnswerGenerationException('Claude API request failed: '.$e->getMessage(), previous: $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new AnswerGenerationException('The model declined to answer this request.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}
