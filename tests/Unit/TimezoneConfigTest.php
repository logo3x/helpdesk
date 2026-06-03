<?php

it('config/app.php define America/Bogota como timezone por default', function () {
    $config = require dirname(__DIR__, 2).'/config/app.php';

    expect($config['timezone'])->toBe('America/Bogota');
});
