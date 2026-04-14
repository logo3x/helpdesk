<?php

namespace App\Livewire\Portal;

use App\Models\ChatSession;
use App\Services\ChatbotService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Asistente virtual')]
class Chatbot extends Component
{
    public string $message = '';

    public ?int $sessionId = null;

    /** @var array<int, array{role: string, content: string}> */
    public array $history = [];

    public bool $isOpen = false;

    public function mount(): void
    {
        $service = app(ChatbotService::class);
        $session = $service->getOrCreateSession(auth()->user());
        $this->sessionId = $session->id;

        $this->history = $session->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        if (empty($this->history)) {
            $this->history[] = [
                'role' => 'assistant',
                'content' => '¡Hola! Soy el asistente virtual de Confipetrol. ¿En qué puedo ayudarte? Puedo guiarte con reset de contraseña, VPN, impresoras y más.',
            ];
        }
    }

    public function send(): void
    {
        $message = trim($this->message);

        if (blank($message)) {
            return;
        }

        $this->message = '';

        // Check for escalation command
        if (str_starts_with(mb_strtolower($message), 'escalar:')) {
            $subject = trim(mb_substr($message, 8));
            $this->escalate($subject);

            return;
        }

        $session = ChatSession::find($this->sessionId);

        if ($session === null || $session->status !== 'active') {
            $session = app(ChatbotService::class)->getOrCreateSession(auth()->user());
            $this->sessionId = $session->id;
        }

        $response = app(ChatbotService::class)->handleMessage($session, $message);

        $this->history[] = ['role' => 'user', 'content' => $message];
        $this->history[] = ['role' => 'assistant', 'content' => $response];

        $this->dispatch('chat-updated');
    }

    public function escalate(string $subject = ''): void
    {
        $session = ChatSession::find($this->sessionId);

        if ($session === null) {
            return;
        }

        $ticket = app(ChatbotService::class)->escalateToTicket(
            $session,
            auth()->user(),
            $subject,
        );

        $this->history[] = [
            'role' => 'assistant',
            'content' => "Tu conversación se ha escalado al ticket **{$ticket->number}**. Un agente de soporte se pondrá en contacto contigo pronto.",
        ];

        $this->dispatch('chat-updated');
    }

    public function render(): View
    {
        return view('livewire.portal.chatbot');
    }
}
