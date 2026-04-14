<?php

namespace App\Services;

use App\Models\ChatFlow;
use App\Models\ChatFlowStat;
use App\Models\ChatSession;

/**
 * Executes predefined chat flows step by step.
 *
 * Each flow has a JSON `steps` array. Each step is an object with:
 * - `prompt`: the message to show the user
 * - `type`: "message" (just display), "input" (wait for response), "action" (server-side)
 * - `action`: optional action identifier (e.g., "create_ticket", "link")
 * - `data`: optional payload for the action
 */
class FlowEngine
{
    /**
     * Start a flow for a session and return the first step message.
     */
    public function start(ChatSession $session, ChatFlow $flow): string
    {
        ChatFlowStat::create([
            'chat_flow_id' => $flow->id,
            'chat_session_id' => $session->id,
            'completed' => false,
            'steps_completed' => 0,
        ]);

        $steps = $flow->steps;

        if (empty($steps)) {
            return 'Este flujo no tiene pasos configurados.';
        }

        return $steps[0]['prompt'] ?? 'Iniciando flujo...';
    }

    /**
     * Advance the flow by one step based on user input.
     * Returns the next step message or null if the flow is complete.
     */
    public function advance(ChatSession $session, ChatFlow $flow, string $userInput): ?string
    {
        $stat = ChatFlowStat::where('chat_session_id', $session->id)
            ->where('chat_flow_id', $flow->id)
            ->first();

        if ($stat === null) {
            return $this->start($session, $flow);
        }

        $steps = $flow->steps;
        $nextIndex = $stat->steps_completed + 1;

        $stat->steps_completed = $nextIndex;

        if ($nextIndex >= count($steps)) {
            $stat->completed = true;
            $stat->save();

            return null; // Flow complete
        }

        $stat->save();

        $step = $steps[$nextIndex];

        return $step['prompt'] ?? null;
    }

    /**
     * Check if a flow is still in progress for a session.
     */
    public function isInProgress(ChatSession $session, ChatFlow $flow): bool
    {
        return ChatFlowStat::where('chat_session_id', $session->id)
            ->where('chat_flow_id', $flow->id)
            ->where('completed', false)
            ->exists();
    }

    /**
     * Get the active flow for a session (if any).
     */
    public function getActiveFlow(ChatSession $session): ?ChatFlow
    {
        $stat = ChatFlowStat::where('chat_session_id', $session->id)
            ->where('completed', false)
            ->with('flow')
            ->first();

        return $stat?->flow;
    }
}
