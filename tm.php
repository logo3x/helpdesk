Mail::raw('Prueba SMTP Helpdesk Confipetrol.', function($m) { $m->to('luis.oviedo@confipetrol.com')->subject('Test SMTP Helpdesk'); }); echo 'OK';
