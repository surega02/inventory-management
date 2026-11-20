<?php

namespace Tests\Feature\Chat;

use App\Chat\LaravelAi\ChatAgent;
use App\Chat\LlmClient;
use App\Chat\LlmException;
use App\Chat\Tools\ChatTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\TestCase;

class LaravelAiClientTest extends TestCase
{
    public function test_runs_tools_and_returns_their_results(): void
    {
        $tool = $this->tool();
        ChatAgent::fake([
            new ToolCall('call-1', 'echo_tool', ['q' => 'laptop']),
            'There is 1 laptop.',
        ]);

        $reply = app(LlmClient::class)->reply('system', [
            ['role' => 'user', 'content' => 'hi'],
            ['role' => 'assistant', 'content' => 'hello'],
        ], 'Which laptops?', [$tool]);

        $this->assertSame('There is 1 laptop.', $reply->text);
        $this->assertSame([['q' => 'laptop']], $tool->calls);
        $this->assertSame([[
            'name' => 'echo_tool',
            'arguments' => ['q' => 'laptop'],
            'result' => ['echo' => 'laptop'],
        ]], $reply->toolCalls);
    }

    public function test_stops_running_tools_after_max_rounds(): void
    {
        $tool = $this->tool();
        ChatAgent::fake(array_fill(0, 6, new ToolCall('call', 'echo_tool', ['q' => 'again'])));

        app(LlmClient::class)->reply('system', [], 'loop forever', [$tool], maxToolRounds: 3);

        $this->assertCount(3, $tool->calls);
    }

    public function test_timeouts_become_llm_timeout_exceptions(): void
    {
        ChatAgent::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        try {
            app(LlmClient::class)->reply('system', [], 'hi', []);
            $this->fail('Expected LlmException.');
        } catch (LlmException $e) {
            $this->assertTrue($e->timedOut);
        }
    }

    /** @return ChatTool&object{calls: list<array<string, mixed>>} */
    private function tool(): ChatTool
    {
        return new class implements ChatTool
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function name(): string
            {
                return 'echo_tool';
            }

            public function description(): string
            {
                return 'Echoes the query.';
            }

            public function schema(JsonSchema $schema): array
            {
                return ['q' => $schema->string()->required()];
            }

            public function handle(array $arguments): array
            {
                $this->calls[] = $arguments;

                return ['echo' => $arguments['q']];
            }
        };
    }
}
