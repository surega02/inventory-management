<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per POST /chat/messages, failed ones included. Append-only; the
 * reply text is not stored (chat history stays in the browser for the MVP).
 *
 * status: ok | refused (rejected before the LLM: invalid input) | error (LLM failed or timed out) | rate_limited
 *
 * @property int $id
 * @property int $user_id
 * @property string $message
 * @property list<array{name: string, arguments: array<string, mixed>}> $tool_calls
 * @property string $status
 * @property string $model
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int|null $latency_ms
 * @property string|null $error_message
 * @property Carbon $created_at
 */
class ChatLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tool_calls' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Writes one row; message and error are clipped so a hostile request cannot bloat the table.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function record(User $user, string $message, string $status, array $extra = []): self
    {
        return self::create([
            'user_id' => $user->id,
            'message' => mb_substr($message, 0, 1000),
            'status' => $status,
            'model' => (string) config('services.llm.model'),
            ...$extra,
            'error_message' => isset($extra['error_message']) ? mb_substr((string) $extra['error_message'], 0, 1000) : null,
        ]);
    }
}
