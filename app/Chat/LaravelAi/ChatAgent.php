<?php

namespace App\Chat\LaravelAi;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/** Internal to LaravelAiClient; history comes from the request, not the SDK's conversation tables. */
class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  list<Message>  $history
     * @param  list<Tool>  $tools
     */
    public function __construct(
        private string $system,
        private array $history,
        private array $tools,
        private int $maxSteps,
        private int $timeoutSeconds,
    ) {}

    public function instructions(): string
    {
        return $this->system;
    }

    public function messages(): iterable
    {
        return $this->history;
    }

    public function tools(): iterable
    {
        return $this->tools;
    }

    public function maxSteps(): int
    {
        return $this->maxSteps;
    }

    public function timeout(): int
    {
        return $this->timeoutSeconds;
    }
}
