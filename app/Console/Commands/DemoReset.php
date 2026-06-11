<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Resetea el estado de los videos demo (kb-demo.spec.cjs y
 * kb-hse-demo-slow.spec.cjs):
 *
 *   1. Borra el KB indicado por --slug (force delete, sin soft delete).
 *   2. Borra todas las sesiones y mensajes de chat de los usuarios de
 *      demo para que el asistente arranque con el saludo limpio en
 *      cada grabación.
 *
 * Se invoca desde el beforeAll de cada spec.
 */
#[Signature('demo:reset {--slug= : Slug del KB demo a borrar antes de grabar}')]
#[Description('Resetea KB de demo + historial del chatbot para grabar video desde cero')]
class DemoReset extends Command
{
    public function handle(): int
    {
        if ($slug = $this->option('slug')) {
            $deleted = KbArticle::where('slug', $slug)->forceDelete();
            $this->info("KB {$slug}: borrado(s) = {$deleted}");
        }

        // Limpia historial de chat de los usuarios de demo. Borramos
        // primero los mensajes (FK), luego las sesiones.
        $userIds = User::whereIn('email', [
            'demo-supervisor@confipetrol.local',
            'demo-final@confipetrol.local',
        ])->pluck('id');

        $sessionIds = ChatSession::whereIn('user_id', $userIds)->pluck('id');

        $msgs = ChatMessage::whereIn('chat_session_id', $sessionIds)->delete();
        $sess = ChatSession::whereIn('id', $sessionIds)->delete();

        $this->info("Chat reset: {$msgs} mensaje(s), {$sess} sesión(es).");

        return self::SUCCESS;
    }
}
