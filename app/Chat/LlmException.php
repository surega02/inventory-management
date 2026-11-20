<?php

namespace App\Chat;

use RuntimeException;

/** The LLM call failed; $timedOut picks 504 llm_timeout over 502 llm_error. */
class LlmException extends RuntimeException
{
    public bool $timedOut = false;

    public static function timedOut(\Throwable $previous): self
    {
        $e = new self('LLM request timed out.', 0, $previous);
        $e->timedOut = true;

        return $e;
    }
}
