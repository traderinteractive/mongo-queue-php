# mongo-queue-php
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/traderinteractive/mongo-queue-php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/traderinteractive/mongo-queue-php/?branch=master)

[![Latest Stable Version](https://poser.pugx.org/traderinteractive/mongo-queue/v/stable)](https://packagist.org/packages/traderinteractive/mongo-queue)
[![Latest Unstable Version](https://poser.pugx.org/traderinteractive/mongo-queue/v/unstable)](https://packagist.org/packages/traderinteractive/mongo-queue)
[![License](https://poser.pugx.org/traderinteractive/mongo-queue/license)](https://packagist.org/packages/traderinteractive/mongo-queue)

[![Total Downloads](https://poser.pugx.org/traderinteractive/mongo-queue/downloads)](https://packagist.org/packages/traderinteractive/mongo-queue)
[![Daily Downloads](https://poser.pugx.org/traderinteractive/mongo-queue/d/daily)](https://packagist.org/packages/traderinteractive/mongo-queue)
[![Monthly Downloads](https://poser.pugx.org/traderinteractive/mongo-queue/d/monthly)](https://packagist.org/packages/traderinteractive/mongo-queue)

PHP message queue using MongoDB as a backend.

## Features

 * Message selection and/or count via MongoDB query
 * Distributes across machines via MongoDB
 * Message priority
 * Delayed messages
 * Running message timeout and redeliver
 * Atomic acknowledge and send together
 * Easy index creation based only on payload

## Simplest use

```php
use TraderInteractive\Mongo\Queue;

$queue = new Queue('mongodb://localhost', 'queues', 'queue');
$queue->send(new Message());
$messages = $queue->get([], ['runningResetDuration' => 60]);
foreach ($messages as $message) {
    // Do something with message

    $queue->ack($message);
}
```

## Composer

To add the library as a local, per-project dependency use [Composer](http://getcomposer.org)! Simply add a
dependency on `traderinteractive/mongo-queue` to your project's `composer.json` file such as:

```sh
composer require traderinteractive/mongo-queue
```

## Documentation

Found in the [source](src/) itself, take a look!

## Contact

Developers may be contacted at:

 * [Pull Requests](https://github.com/traderinteractive/mongo-queue-php/pulls)
 * [Issues](https://github.com/traderinteractive/mongo-queue-php/issues)

## Contributing
If you would like to contribute, please use our build process for any changes
and after the build passes, send us a pull request on github!
```sh
./vendor/bin/phpunit
./vendor/bin/phpcs
```

There is also a [docker](http://www.docker.com/)-based
[fig](http://www.fig.sh/) configuration that will execute the build inside a
docker container.  This is an easy way to build the application:
```sh
fig run build
```
