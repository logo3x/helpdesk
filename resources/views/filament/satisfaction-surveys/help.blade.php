<div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
    <div>
        <h4 class="font-semibold text-gray-950 dark:text-white">¿Cuándo se envía?</h4>
        <p>Cuando un ticket se <strong>cierra</strong>, el solicitante recibe una encuesta en la campanita y por correo, con un enlace de un solo uso.</p>
    </div>

    <div>
        <h4 class="font-semibold text-gray-950 dark:text-white">¿Qué se pregunta?</h4>
        <p>Seis preguntas, cada una de 1 a 5 estrellas:</p>
        <ul class="ms-5 list-disc">
            @foreach (\App\Models\SatisfactionSurvey::DIMENSIONS as $label)
                <li>{{ $label }}</li>
            @endforeach
        </ul>
        <p class="mt-1">La <strong>calificación</strong> del ticket es el promedio redondeado de las seis. El usuario además puede dejar un comentario.</p>
    </div>

    <div>
        <h4 class="font-semibold text-gray-950 dark:text-white">Encuestas auto-positivas</h4>
        <p>
            Si el solicitante no responde en {{ $autoPositiveDays }} día{{ $autoPositiveDays === 1 ? '' : 's' }}, el sistema marca la encuesta con
            <strong>5★</strong> y agrega una nota en el comentario. Estas encuestas no califican las seis preguntas, así que no cuentan
            en los promedios por dimensión ni en la tasa de respuesta.
        </p>
    </div>

    <div>
        <h4 class="font-semibold text-gray-950 dark:text-white">Filtros y estadísticas</h4>
        <p>
            Las tarjetas superiores se recalculan con los filtros y la búsqueda activos. Por ejemplo, filtra por un agente para ver su
            promedio, o elige «Respondida por el usuario» para excluir las auto-positivas. Con el botón de columnas de la tabla
            puedes mostrar la calificación de cada pregunta y el comentario.
        </p>
    </div>
</div>
