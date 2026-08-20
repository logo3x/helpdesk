<?php

return [
    /**
     * Días de anticipación con los que el job NotifyDueMaintenancesJob
     * alerta al agente asignado. Default: 30.
     */
    'alert_days_before' => (int) env('MAINTENANCE_ALERT_DAYS_BEFORE', 30),
];
