<?php

namespace App\Chat;

use App\Chat\Tools\ChatTool;

/**
 * Provider-neutral boundary to the LLM. The client runs the tool loop itself
 * (at most $maxToolRounds rounds, then one final answer) but never knows who
 * the user is: tools arrive already bound to the actor from the session.
 */
interface LlmClient
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history  oldest first, sent by the client
     * @param  list<ChatTool>  $tools
     *
     * @throws LlmException
     */
    public function reply(string $system, array $history, string $message, array $tools, int $maxToolRounds = 3): LlmReply;
}
