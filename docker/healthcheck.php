<?php

/*
 | Contrôle de santé du conteneur : interroge /up (Laravel + base + cache) en
 | local, avec l'hôte d'APP_URL pour passer TrustHosts. Code 0 = sain.
 */
$host = parse_url((string) getenv('APP_URL'), PHP_URL_HOST) ?: 'localhost';
$port = getenv('HEALTHCHECK_PORT') ?: '80';

$context = stream_context_create(['http' => [
    'header' => "Host: {$host}\r\nUser-Agent: docker-healthcheck",
    'timeout' => 5,
]]);

exit(@file_get_contents("http://127.0.0.1:{$port}/up", false, $context) === false ? 1 : 0);
