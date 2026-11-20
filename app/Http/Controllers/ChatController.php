<?php

namespace App\Http\Controllers;

use App\Chat\ChatOrchestrator;
use App\Chat\LlmException;
use App\Models\ChatLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /chat/messages (JSON only). Rate limiting is the `throttle:chat` limiter
 * (AppServiceProvider), which also logs and formats the 429.
 */
class ChatController extends Controller
{
    public const MAX_MESSAGE = 500;

    public const MAX_HISTORY = 6;

    public const MAX_HISTORY_CONTENT = 2000;

    public function __invoke(Request $request, ChatOrchestrator $orchestrator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:'.self::MAX_MESSAGE],
            'history' => ['nullable', 'array', 'max:'.self::MAX_HISTORY],
            'history.*' => ['array:role,content'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:'.self::MAX_HISTORY_CONTENT],
        ]);

        if ($validator->fails()) {
            ChatLog::record($user, is_string($request->input('message')) ? $request->input('message') : '', 'refused', [
                'error_message' => 'validation_failed: '.implode(' ', $validator->errors()->all()),
            ]);

            return self::error(422, 'validation_failed', $validator->errors()->first());
        }

        /** @var array{message: string, history?: array<array{role: 'user'|'assistant', content: string}>|null} $data */
        $data = $validator->validated();

        try {
            return response()->json($orchestrator->handle($user, $data['message'], array_values($data['history'] ?? [])));
        } catch (LlmException $e) {
            report($e);

            return $e->timedOut
                ? self::error(504, 'llm_timeout', 'The assistant took too long to answer. Please try again.')
                : self::error(502, 'llm_error', 'The assistant is unavailable right now. Please try again in a moment.');
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $headers
     */
    public static function error(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, ...$extra]], $status, $headers);
    }
}
