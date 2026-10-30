<?php

it('agrega los encabezados de seguridad a las respuestas', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('X-Powered-By');
});

it('envía HSTS solo cuando la petición llega por HTTPS', function () {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});
