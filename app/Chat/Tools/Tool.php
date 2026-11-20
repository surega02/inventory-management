<?php

namespace App\Chat\Tools;

use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Base for the six read-only tools. The actor is fixed at construction (from the
 * session); arguments come straight from the LLM and are never trusted:
 *
 * - bad arguments become {"error": "invalid_argument", "detail": ...} so the LLM can retry;
 * - every output row is built field by field (never toArray), so no IDs, emails or
 *   prices leak by accident; free text is clipped;
 * - lists carry at most MAX_ROWS rows plus total and truncated.
 */
abstract class Tool implements ChatTool
{
    public const MAX_ROWS = 20;

    public const DEFAULT_ROWS = 10;

    /** Longest free-text value (description, search term) sent either way. */
    public const TEXT_LIMIT = 200;

    public function __construct(protected readonly User $actor) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<mixed>
     */
    abstract protected function run(array $arguments): array;

    final public function handle(array $arguments): array
    {
        try {
            return $this->run($arguments);
        } catch (InvalidArgumentException $e) {
            return ['error' => 'invalid_argument', 'detail' => $e->getMessage()];
        }
    }

    /** @param array<string, mixed> $args */
    protected function string(array $args, string $key, bool $required = false): ?string
    {
        $value = $args[$key] ?? null;

        if ($value !== null && ! is_scalar($value)) {
            throw new InvalidArgumentException("{$key} must be a string.");
        }

        $value = Str::of((string) $value)->squish()->limit(self::TEXT_LIMIT, '')->value();

        if ($value === '') {
            return $required ? throw new InvalidArgumentException("{$key} is required.") : null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $allowed
     */
    protected function oneOf(array $args, string $key, array $allowed): ?string
    {
        $value = $this->string($args, $key);

        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        return in_array($value, $allowed, true)
            ? $value
            : throw new InvalidArgumentException("Unknown {$key} \"{$value}\". Use one of: ".implode(', ', $allowed).'.');
    }

    /** @param array<string, mixed> $args */
    protected function bool(array $args, string $key): bool
    {
        return filter_var($args[$key] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /** @param array<string, mixed> $args Y-m-d only */
    protected function date(array $args, string $key): ?string
    {
        $value = $this->string($args, $key);

        if ($value === null) {
            return null;
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new InvalidArgumentException("{$key} must be a date as YYYY-MM-DD.");
        }

        return $value;
    }

    /** @param array<string, mixed> $args */
    protected function limit(array $args): int
    {
        $limit = filter_var($args['limit'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return min(max($limit ?? self::DEFAULT_ROWS, 1), self::MAX_ROWS);
    }

    /**
     * @param  iterable<mixed>  $items
     * @param  callable(mixed): array<string, mixed>  $row
     * @return array{total: int, truncated: bool, items: list<array<string, mixed>>}
     */
    protected function rows(int $total, iterable $items, callable $row): array
    {
        $out = [];
        foreach ($items as $item) {
            if (count($out) === self::MAX_ROWS) {
                break;
            }
            $out[] = $row($item);
        }

        return ['total' => $total, 'truncated' => $total > count($out), 'items' => $out];
    }

    protected static function text(?string $value): ?string
    {
        return $value === null ? null : Str::limit(Str::squish($value), self::TEXT_LIMIT);
    }
}
