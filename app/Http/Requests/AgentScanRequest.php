<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:desktop,laptop,server,printer,other'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'os_name' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'os_architecture' => ['nullable', 'string', 'max:20'],
            'cpu_cores' => ['nullable', 'integer', 'min:1'],
            'cpu_model' => ['nullable', 'string', 'max:255'],
            'ram_mb' => ['nullable', 'integer', 'min:0'],
            'disk_total_gb' => ['nullable', 'integer', 'min:0'],
            'gpu_info' => ['nullable', 'string', 'max:500'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'mac_address' => ['nullable', 'string', 'max:17'],
            'software' => ['nullable', 'array', 'max:500'],
            'software.*.name' => ['required_with:software', 'string', 'max:255'],
            'software.*.version' => ['nullable', 'string', 'max:100'],
            'software.*.publisher' => ['nullable', 'string', 'max:255'],
            'software.*.install_date' => ['nullable', 'date'],
        ];
    }
}
