<?php

namespace App\Chat;

final readonly class LlmReply
{
    /**
     * @param  list<array{name: string, arguments: array<string, mixed>, result: mixed}>  $toolCalls  in call order; the orchestrator builds links and chat_logs from these
     */
    public function __construct(
        public string $text,
        public array $toolCalls,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
