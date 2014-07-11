#!/usr/bin/env php
<?php
$returnStatus = null;
passthru('docker build --tag=mongo-queue-php ' . __DIR__, $returnStatus);
if ($returnStatus !== 0) {
    exit(1);
}

passthru(
    'docker run --name mongo-queue-php-build --tty --rm --volume ' . __DIR__ . ':/code ' . createLink('mongo') . ' mongo-queue-php',
    $returnStatus
);
if ($returnStatus !== 0) {
    exit(1);
}

function createLink($name)
{
    $returnStatus = null;
    passthru("docker inspect --format='{{.Name}}' mongo-queue-php-{$name}", $returnStatus);
    if ($returnStatus !== 0) {
        passthru("docker run --name mongo-queue-php-{$name} --detach {$name}", $returnStatus);
        if ($returnStatus !== 0) {
            exit(1);
        }
    }

    return "--link mongo-queue-php-{$name}:{$name}";
}
