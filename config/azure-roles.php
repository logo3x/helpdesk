<?php

/**
 * Mapping of Azure AD (Entra ID) group Object IDs to Spatie roles.
 *
 * When a user logs in via SSO, the system reads their Azure AD groups
 * and assigns the first matching Spatie role from this mapping.
 * If no group matches, the user gets 'usuario_final' by default.
 *
 * To configure:
 * 1. Go to Azure Portal → Entra ID → Groups
 * 2. Copy the Object ID of each group
 * 3. Map it to the corresponding Spatie role name below
 */

return [

    // 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' => 'super_admin',
    // 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' => 'admin',
    // 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' => 'supervisor_soporte',
    // 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' => 'agente_soporte',
    // 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' => 'tecnico_campo',
    // 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' => 'editor_kb',

    // Default role for users not in any mapped group:
    '_default' => 'usuario_final',

];
