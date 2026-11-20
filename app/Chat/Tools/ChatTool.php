<?php

namespace App\Chat\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * A read-only chatbot tool. Implementations receive the actor through their
 * constructor (from the session) and must never accept identity from $arguments.
 */
interface ChatTool
{
    /** snake_case name the LLM sees, e.g. search_assets. */
    public function name(): string;

    public function description(): string;

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array;

    /**
     * @param  array<string, mixed>  $arguments  untrusted, straight from the LLM
     * @return array<mixed> already sanitized and capped at 20 rows
     */
    public function handle(array $arguments): array;
}
