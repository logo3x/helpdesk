<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hostname' => ['nullable', 'string', 'max:255'],
            'os_name' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'cpu_cores' => ['nullable', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['nullable', 'numeric', 'min:0', 'max:4096'],
            'gpu_info' => ['nullable', 'string', 'max:500'],
            'screen_resolution' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'user_agent' => ['nullable', 'string', 'max:500'],
        ];
    }
}
