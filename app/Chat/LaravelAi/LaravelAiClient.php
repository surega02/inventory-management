<?php

namespace App\Chat\LaravelAi;

use App\Chat\LlmClient;
use App\Chat\LlmException;
use App\Chat\LlmReply;
use App\Chat\Tools\ChatTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Tools\Request;
use Throwable;

class LaravelAiClient implements LlmClient
{
    public function __construct(
        private string $provider,
        private string $model,
        private int $timeoutSeconds,
    ) {}

    public function reply(string $system, array $history, string $message, array $tools, int $maxToolRounds = 3): LlmReply
    {
        $agent = new ChatAgent(
            $system,
            array_map(fn (array $m) => $m['role'] === 'assistant'
                ? new AssistantMessage($m['content'])
                : new UserMessage($m['content']), $history),
            array_map($this->adapt(...), $tools),
            maxSteps: $maxToolRounds + 1, // the last step must answer, not call tools
            timeoutSeconds: $this->timeoutSeconds,
        );

        try {
            $response = $agent->prompt($message, provider: $this->provider, model: $this->model);
        } catch (ConnectionException|ProviderConnectionException $e) {
            throw str_contains(strtolower($e->getMessage()), 'timed out')
                ? LlmException::timedOut($e)
                : new LlmException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new LlmException($e->getMessage(), 0, $e);
        }

        return new LlmReply(
            text: $response->text,
            toolCalls: array_values(array_map(fn (ToolResult $r) => [
                'name' => $r->name,
                'arguments' => $r->arguments,
                'result' => is_string($r->result) ? json_decode($r->result, true) : $r->result,
            ], $response->toolResults->all())),
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
        );
    }

    private function adapt(ChatTool $tool): Tool
    {
        return new class($tool) implements Tool
        {
            public function __construct(private ChatTool $tool) {}

            public function name(): string
            {
                return $this->tool->name();
            }

            public function description(): string
            {
                return $this->tool->description();
            }

            public function schema(JsonSchema $schema): array
            {
                return $this->tool->schema($schema);
            }

            public function handle(Request $request): string
            {
                return (string) json_encode($this->tool->handle($request->all()));
            }
        };
    }
}
