<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

/**
 * Representación inmutable de un empleado tal como lo recibimos de Kactus.
 *
 * Esta clase aísla el resto del código del shape real del payload de
 * Kactus — cuando Hermes confirme la API, sólo se ajusta el factory
 * `fromKactusPayload()` y los consumidores (KactusService::syncToUser)
 * no se enteran.
 */
final class KactusEmployee
{
    public function __construct(
        public string $kactusId,
        public string $identification,
        public string $name,
        public ?string $email,
        public ?string $position,
        public ?string $phone,
        public ?string $departmentName,
        public string $status,
        public ?CarbonImmutable $hiredAt,
        public ?CarbonImmutable $terminatedAt,
        public array $rawPayload,
    ) {}

    /**
     * Construye el DTO desde el payload crudo de Kactus.
     *
     * Mapea los nombres de campo esperados — si la API real usa otros
     * nombres, este es el único lugar a tocar.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromKactusPayload(array $payload): self
    {
        $fullName = trim(($payload['first_name'] ?? '').' '.($payload['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = (string) ($payload['full_name'] ?? $payload['name'] ?? 'Sin nombre');
        }

        return new self(
            kactusId: (string) ($payload['employee_id'] ?? $payload['id']),
            identification: (string) ($payload['document_number'] ?? $payload['identification'] ?? ''),
            name: $fullName,
            email: $payload['email'] ?? null,
            position: $payload['position'] ?? $payload['job_title'] ?? null,
            phone: $payload['phone'] ?? $payload['mobile'] ?? null,
            departmentName: $payload['department'] ?? $payload['department_name'] ?? null,
            status: self::normalizeStatus($payload['status'] ?? 'active'),
            hiredAt: isset($payload['hired_at']) ? CarbonImmutable::parse($payload['hired_at']) : null,
            terminatedAt: isset($payload['terminated_at']) ? CarbonImmutable::parse($payload['terminated_at']) : null,
            rawPayload: $payload,
        );
    }

    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    private static function normalizeStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'active', 'activo' => 'active',
            'terminated', 'retirado', 'inactivo', 'inactive' => 'terminated',
            'on_leave', 'licencia', 'incapacidad' => 'on_leave',
            default => 'active',
        };
    }
}
