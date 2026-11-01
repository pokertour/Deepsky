<?php

return [
    // The provider and keys are managed by the existing encrypted admin settings.
    'hostnames' => array_values(array_filter(array_map('trim', explode(',', env('TURNSTILE_HOSTNAMES', 'deepsky.nexus'))))),
];
