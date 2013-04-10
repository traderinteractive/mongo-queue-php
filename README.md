#mongo-queue-php
[![Build Status](https://travis-ci.org/dominionenterprises/mongo-queue-php.png)](https://travis-ci.org/dominionenterprises/mongo-queue-php)

PHP message queue using MongoDB as a backend.
Adheres to the 1.0.0 [specification](https://github.com/dominionenterprises/mongo-queue-specification).

##Features

 * Message selection and/or count via MongoDB query
 * Distributes across machines via MongoDB
 * Multi language support through the [specification](https://github.com/dominionenterprises/mongo-queue-specification)
 * Message priority
 * Delayed messages
 * Running message timeout and redeliver
 * Atomic acknowledge and send together
 * Easy index creation based only on payload

##Simplest use

```php
use DominionEnterprises\Mongo\Queue;

$queue = new Queue('mongodb://localhost', 'queues', 'queue');
$queue->send(array());
$message = $queue->get(array(), 60);
$queue->ack($message);
```

##Composer

To add the library as a local, per-project dependency use [Composer](http://getcomposer.org)! Simply add a dependency on
`dominionenterprises/mongo-queue-php` to your project's `composer.json` file such as:

```json
{
    "require": {
        "dominionenterprises/mongo-queue-php": "1.*"
    }
}
```

##Documentation

Found in the [source](src/DominionEnterprises/Mongo/Queue.php) itself, take a look!

##Contact

Developers may be contacted at:

 * [Pull Requests](https://github.com/dominionenterprises/mongo-queue-php/pulls)
 * [Issues](https://github.com/dominionenterprises/mongo-queue-php/issues)

##Tests

Install and start [mongodb](http://www.mongodb.org).
With a checkout of the code get [Composer](http://getcomposer.org) in your PATH and run:

```bash
php build.php
```
