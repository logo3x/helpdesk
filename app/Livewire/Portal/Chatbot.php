<?php

namespace App\Livewire\Portal;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
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

    /**
     * Estado del flujo guiado de creación de ticket:
     *   ''                     → libre (KB, flujos rule-based, LLM)
     *   'awaiting_subject'     → esperando descripción del problema
     *   'awaiting_department'  → esperando selección de departamento
     */
    public string $escalationState = '';

    public string $escalationSubject = '';

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
                'content' => '¡Hola! Soy el asistente virtual de Confipetrol. ¿En qué puedo ayudarte? Puedo guiarte con reset de contraseña, VPN, impresoras y más. Si necesitas crear un ticket, escribe **"crear ticket"**.',
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

        $session = $this->resolveSession();

        // Estado 1: esperando el resumen del problema
        if ($this->escalationState === 'awaiting_subject') {
            $this->escalationSubject = $message;
            $this->appendUser($session, $message);
            $this->appendAssistant($session, $this->departmentPrompt());
            $this->escalationState = 'awaiting_department';
            $this->dispatch('chat-updated');

            return;
        }

        // Estado 2: esperando selección de departamento
        if ($this->escalationState === 'awaiting_department') {
            $this->appendUser($session, $message);
            $departmentId = $this->parseDepartmentChoice($message);

            if ($departmentId === null) {
                $this->appendAssistant(
                    $session,
                    "No reconocí esa opción. Por favor escribe un número del **1 al 5** o el nombre del departamento:\n\n"
                    .$this->departmentList(),
                );
                $this->dispatch('chat-updated');

                return;
            }

            $this->createTicketAndFinish($session, $departmentId);
            $this->dispatch('chat-updated');

            return;
        }

        // Compatibilidad con el comando antiguo "escalar: [resumen]"
        if (str_starts_with(mb_strtolower($message), 'escalar:')) {
            $subject = trim(mb_substr($message, 8));
            $this->escalate($subject);

            return;
        }

        // Flujo normal (KB, flows, LLM)
        $response = app(ChatbotService::class)->handleMessage($session, $message);

        $this->history[] = ['role' => 'user', 'content' => $message];
        $this->history[] = ['role' => 'assistant', 'content' => $response];

        // Si la respuesta es el prompt inicial de escalación, entrar al flujo guiado
        if (str_contains($response, 'Cuéntame **brevemente**')) {
            $this->escalationState = 'awaiting_subject';
        }

        $this->dispatch('chat-updated');
    }

    /**
     * Flujo legacy: usuario escribió "escalar: resumen" — crea el ticket
     * sin preguntar departamento (queda sin asignar).
     */
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
            'content' => $this->successMessage($ticket->number, null),
        ];

        $this->resetEscalation();
        $this->dispatch('chat-updated');
    }

    protected function createTicketAndFinish(ChatSession $session, int $departmentId): void
    {
        $ticket = app(ChatbotService::class)->escalateToTicket(
            $session,
            auth()->user(),
            $this->escalationSubject,
            $departmentId,
        );

        $departmentName = Department::find($departmentId)?->name;
        $this->appendAssistant($session, $this->successMessage($ticket->number, $departmentName));
        $this->resetEscalation();
    }

    protected function resetEscalation(): void
    {
        $this->escalationState = '';
        $this->escalationSubject = '';
    }

    protected function resolveSession(): ChatSession
    {
        // SEGURIDAD: filtrar por user_id para que un usuario no pueda
        // inyectar un $sessionId de otro user vía Livewire payload y
        // escribir mensajes en esa transcripción ajena.
        $session = ChatSession::where('id', $this->sessionId)
            ->where('user_id', auth()->id())
            ->first();

        if ($session === null || $session->status !== 'active') {
            $session = app(ChatbotService::class)->getOrCreateSession(auth()->user());
            $this->sessionId = $session->id;
        }

        return $session;
    }

    protected function appendUser(ChatSession $session, string $content): void
    {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $content,
        ]);

        $this->history[] = ['role' => 'user', 'content' => $content];
    }

    protected function appendAssistant(ChatSession $session, string $content): void
    {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $content,
        ]);

        $this->history[] = ['role' => 'assistant', 'content' => $content];
    }

    protected function departmentPrompt(): string
    {
        return "Perfecto, registré: *\"{$this->escalationSubject}\"*\n\n"
            ."**¿A qué departamento va dirigido?** Escribe el número:\n\n"
            .$this->departmentList();
    }

    protected function departmentList(): string
    {
        return Department::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn ($d, int $i) => '- **'.($i + 1).'** — '.$d->name)
            ->implode("\n");
    }

    /**
     * Acepta "1" – "N" o el nombre exacto / slug del departamento.
     */
    protected function parseDepartmentChoice(string $input): ?int
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->values();

        $trimmed = trim($input);

        if (ctype_digit($trimmed)) {
            $index = ((int) $trimmed) - 1;

            // Guard contra valores fuera de rango (0, negativos, > count).
            if ($index < 0 || $index >= $departments->count()) {
                return null;
            }

            return $departments[$index]->id;
        }

        $lower = mb_strtolower($trimmed);
        foreach ($departments as $d) {
            if (mb_strtolower($d->name) === $lower || $d->slug === $lower) {
                return $d->id;
            }
        }

        // Match parcial por nombre (ej: "ti" dentro de "Tecnología de la Información")
        foreach ($departments as $d) {
            if (str_contains(mb_strtolower($d->name), $lower) || str_contains($d->slug, $lower)) {
                return $d->id;
            }
        }

        return null;
    }

    protected function successMessage(string $ticketNumber, ?string $departmentName): string
    {
        $deptLine = $departmentName
            ? "Fue asignado al departamento **{$departmentName}**."
            : 'Un agente se pondrá en contacto contigo pronto.';

        return "✅ ¡Listo! Creé el ticket **{$ticketNumber}**.\n\n"
            .$deptLine."\n\n"
            .'Puedes ver el detalle y hacer seguimiento en [Mis tickets](/portal/tickets).';
    }

    public function render(): View
    {
        return view('livewire.portal.chatbot');
    }
}
