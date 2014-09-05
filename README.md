#mongo-queue-php
[![Build Status](http://img.shields.io/travis/dominionenterprises/mongo-queue-php.svg?style=flat)](https://travis-ci.org/dominionenterprises/mongo-queue-php)
[![Scrutinizer Code Quality](http://img.shields.io/scrutinizer/g/dominionenterprises/mongo-queue-php.svg?style=flat)](https://scrutinizer-ci.com/g/dominionenterprises/mongo-queue-php/)
[![Code Coverage](http://img.shields.io/coveralls/dominionenterprises/mongo-queue-php.svg?style=flat)](https://coveralls.io/r/dominionenterprises/mongo-queue-php)

[![Latest Stable Version](http://img.shields.io/packagist/v/dominionenterprises/mongo-queue-php.svg?style=flat)](https://packagist.org/packages/dominionenterprises/mongo-queue-php)
[![Total Downloads](http://img.shields.io/packagist/dt/dominionenterprises/mongo-queue-php.svg?style=flat)](https://packagist.org/packages/dominionenterprises/mongo-queue-php)
[![License](http://img.shields.io/packagist/l/dominionenterprises/mongo-queue-php.svg?style=flat)](https://packagist.org/packages/dominionenterprises/mongo-queue-php)

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

Found in the [source](src/Queue.php) itself, take a look!

##Contact

Developers may be contacted at:

 * [Pull Requests](https://github.com/dominionenterprises/mongo-queue-php/pulls)
 * [Issues](https://github.com/dominionenterprises/mongo-queue-php/issues)

##Contributing

If you would like to contribute, please use our build process for any changes
and after the build passes, send us a pull request on github!  The build
requires a running mongo.  The URI to mongo can be specified via an environment
variable or left to its default (localhost on the default port, 27017):
```sh
TESTING_MONGO_URL=mongodb://127.0.0.1:27017 ./build.php
```

There is also a [docker](http://www.docker.com/)-based
[fig](http://www.fig.sh/) configuration that will standup a docker container
for the database, execute the build inside a docker container, and then
terminate everything.  This is an easy way to build the application:
```sh
fig run build
```
