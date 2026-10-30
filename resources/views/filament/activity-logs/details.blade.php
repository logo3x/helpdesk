@php
    $changes = $activity->attribute_changes;
    $new = $changes?->get('attributes') ?? [];
    $old = $changes?->get('old') ?? [];
    $fields = array_unique(array_merge(array_keys($new), array_keys($old)));
    $properties = $activity->properties?->except(['attributes', 'old'])->all() ?? [];
    $format = function ($value) {
        if (is_null($value) || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return \Illuminate\Support\Str::limit(strip_tags((string) $value), 300);
    };
@endphp

<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Usuario</dt>
            <dd>{{ $activity->causer?->name ?? 'Sistema' }} @if ($activity->causer?->email) <span class="text-gray-500">({{ $activity->causer->email }})</span> @endif</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Registro afectado</dt>
            <dd>{{ $subject }}</dd>
        </div>
        @foreach ($properties as $key => $value)
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ ['ip' => 'IP', 'user_agent' => 'Navegador', 'email' => 'Correo usado'][$key] ?? $key }}</dt>
                <dd class="break-all">{{ $format($value) }}</dd>
            </div>
        @endforeach
    </dl>

    @if (count($fields) > 0)
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full text-left">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2">Campo</th>
                        @if (count($old) > 0)
                            <th class="px-3 py-2">Antes</th>
                        @endif
                        <th class="px-3 py-2">{{ count($old) > 0 ? 'Después' : 'Valor' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($fields as $field)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $field }}</td>
                            @if (count($old) > 0)
                                <td class="px-3 py-2 text-danger-600 dark:text-danger-400">{{ $format($old[$field] ?? null) }}</td>
                            @endif
                            <td class="px-3 py-2 text-success-600 dark:text-success-400">{{ $format($new[$field] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">Esta acción no tiene cambios de campos para mostrar.</p>
    @endif
</div>
