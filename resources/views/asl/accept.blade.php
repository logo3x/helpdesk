<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-neutral-100 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="flex w-full max-w-2xl flex-col gap-6">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium">
                <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol" class="block h-12 w-auto object-contain dark:hidden" />
                <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="hidden h-12 w-auto object-contain dark:block" />
                <span class="sr-only">{{ config('app.name', 'Confipetrol') }}</span>
            </a>

            <div class="rounded-xl border bg-white text-stone-800 shadow-xs dark:border-stone-800 dark:bg-stone-950 dark:text-stone-200">
                <div class="px-8 py-8">
                    <h1 class="text-xl font-semibold">Acuerdo de Servicio (ASL)</h1>
                    <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">
                        Hola {{ auth()->user()->name ?? '' }}, antes de continuar necesitamos que aceptes el acuerdo
                        de uso del Helpdesk de Confipetrol.
                    </p>

                    <div class="mt-6 max-h-72 overflow-y-auto rounded-lg border border-stone-200 bg-stone-50 p-4 text-sm leading-relaxed dark:border-stone-800 dark:bg-stone-900">
                        <p class="font-semibold">Términos de uso del Helpdesk Confipetrol</p>

                        <ol class="ml-4 mt-3 list-decimal space-y-2">
                            <li>
                                El acceso al Helpdesk es personal e intransferible. No compartas credenciales con
                                terceros, incluso dentro de la misma área.
                            </li>
                            <li>
                                La información de tickets (descripciones, adjuntos, comentarios) puede contener datos
                                sensibles o confidenciales de Confipetrol. Solo úsala para los fines de la solicitud.
                            </li>
                            <li>
                                Los activos del inventario asignados a tu custodia se reciben en buen estado y bajo
                                tu responsabilidad. Repórtalos como dañados o extraviados ante el equipo de IT
                                inmediatamente.
                            </li>
                            <li>
                                Las interacciones con el chatbot pueden ser procesadas por un proveedor de IA externo
                                con fines de generar la respuesta. No incluyas contraseñas, números de tarjeta ni
                                información personal de terceros.
                            </li>
                            <li>
                                Confipetrol audita el uso del sistema. Cualquier actividad maliciosa, evasión de
                                controles o intento de acceso no autorizado es causal disciplinaria conforme al
                                Reglamento Interno de Trabajo.
                            </li>
                            <li>
                                Al aceptar este acuerdo confirmas que has leído y comprendido las condiciones
                                anteriores y te comprometes a respetarlas durante el uso del Helpdesk.
                            </li>
                        </ol>

                        <p class="mt-4 text-xs italic text-stone-500 dark:text-stone-500">
                            Este texto es un acuerdo de referencia. El departamento Legal de Confipetrol puede
                            actualizar los términos en cualquier momento.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('asl.accept') }}" class="mt-6">
                        @csrf
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" required class="mt-1 rounded border-stone-300" />
                            <span>
                                He leído y acepto los términos del Acuerdo de Servicio del Helpdesk Confipetrol.
                            </span>
                        </label>

                        <div class="mt-6 flex items-center justify-between gap-3">
                            <a href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                               class="text-sm text-stone-500 hover:text-stone-700 dark:hover:text-stone-300">
                                Cerrar sesión
                            </a>

                            <button type="submit"
                                    class="inline-flex items-center justify-center rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                Aceptar y continuar
                            </button>
                        </div>
                    </form>

                    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
