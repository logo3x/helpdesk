<div class="space-y-5 text-sm">
    <div>
        <div class="mb-2 font-semibold text-zinc-800 dark:text-zinc-100">
            Paso 1 — Genera un token
        </div>
        <p class="text-zinc-600 dark:text-zinc-300">
            Click en <span class="font-semibold">"Generar token del agente"</span> arriba, elige el usuario dueño y copia el token (solo se muestra una vez).
        </p>
    </div>

    <div>
        <div class="mb-2 font-semibold text-zinc-800 dark:text-zinc-100">
            Paso 2 — En cada PC, abre PowerShell como administrador y pega
        </div>
        <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs text-zinc-100 dark:bg-black"><code>iex (irm "{{ $installUrl }}?token=PEGA_AQUI_TU_TOKEN")</code></pre>
        <p class="mt-2 text-xs text-zinc-500">
            Reemplaza <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">PEGA_AQUI_TU_TOKEN</code> por el token del paso 1 (formato <code>123|abcd...</code>).
        </p>
    </div>

    <div>
        <div class="mb-2 font-semibold text-zinc-800 dark:text-zinc-100">
            Lo que pasa automáticamente
        </div>
        <ol class="list-decimal space-y-1 pl-5 text-zinc-600 dark:text-zinc-300">
            <li>Descarga el agente a <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">C:\ProgramData\HelpdeskConfipetrol\</code></li>
            <li>Guarda el token de forma segura</li>
            <li>Crea una tarea programada semanal (lunes 9 AM, como SYSTEM)</li>
            <li>Dispara un primer scan inmediato — el equipo aparecerá en el inventario en segundos</li>
        </ol>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-200">
        <div class="font-semibold mb-1">📋 Despliegue masivo (>50 PCs)</div>
        Configura el comando como GPO Startup Script o usa Intune / SCCM con el mismo one-liner. Un solo token compartido sirve para toda la flota.
    </div>

    <div class="text-xs text-zinc-500">
        Si prefieres revisar el script antes de ejecutar, descárgalo con el botón <span class="font-semibold">"Ver script .ps1"</span>.
    </div>
</div>
