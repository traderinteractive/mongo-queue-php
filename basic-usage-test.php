<?php

require_once 'vendor/autoload.php';

use TraderInteractive\Mongo\Message;
use TraderInteractive\Mongo\Queue;

$queue = new Queue('mongodb://localhost', 'queues', 'queue');
$testMessage = new Message();
$testMessage = $testMessage->withPayload([
    'x' => 1,
]);

$queue->send($testMessage);

$messages = $queue->get([], ['runningResetDuration' => 60]);
foreach ($messages as $message) {
    // Do something with message
    var_dump($message->getPayload());

    $queue->ack($message);
}
