<?php
/**
 * Defines the DominionEnterprises\Mongo\Queue class.
 */

namespace DominionEnterprises\Mongo;

/**
 * Abstraction of mongo db collection as priority queue.
 *
 * Tied priorities are ordered by time. So you may use a single priority for normal queuing (default args exist for this purpose).
 * Using a random priority achieves random get()
 */
final class Queue
{
    const MONGO_INT32_MAX = 2147483647;//2147483648 can overflow in php mongo without using the MongoInt64

    /**
     * mongo collection to use for queue.
     *
     * @var \MongoCollection
     */
    private $_collection;

    /**
     * Construct queue.
     *
     * @param string $url the mongo url
     * @param string $db the mongo db name
     * @param string $collection the collection name to use for the queue
     *
     * @throws \InvalidArgumentException $url, $db or $collection was not a string
     */
    public function __construct($url, $db, $collection)
    {
        if (!is_string($url)) {
            throw new \InvalidArgumentException('$url was not a string');
        }

        if (!is_string($db)) {
            throw new \InvalidArgumentException('$db was not a string');
        }

        if (!is_string($collection)) {
            throw new \InvalidArgumentException('$collection was not a string');
        }

        $mongo = new \MongoClient($url);
        $mongoDb = $mongo->selectDB($db);
        $this->_collection = $mongoDb->selectCollection($collection);
    }

    /**
     * Ensure an index for the get() method.
     *
     * @param array $beforeSort fields in get() call to index before the sort field in same format as \MongoCollection::ensureIndex()
     * @param array $afterSort fields in get() call to index after the sort field in same format as \MongoCollection::ensureIndex()
     *
     * @return void
     *
     * @throws \InvalidArgumentException value of $beforeSort or $afterSort is not 1 or -1 for ascending and descending
     * @throws \InvalidArgumentException key in $beforeSort or $afterSort was not a string
     */
    public function ensureGetIndex(array $beforeSort = array(), array $afterSort = array())
    {
        //using general rule: equality, sort, range or more equality tests in that order for index
        $completeFields = array('running' => 1);

        foreach ($beforeSort as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('key in $beforeSort was not a string');
            }

            if ($value !== 1 && $value !== -1) {
                throw new \InvalidArgumentException('value of $beforeSort is not 1 or -1 for ascending and descending');
            }

            $completeFields["payload.{$key}"] = $value;
        }

        $completeFields['priority'] = 1;
        $completeFields['created'] = 1;

        foreach ($afterSort as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('key in $afterSort was not a string');
            }

            if ($value !== 1 && $value !== -1) {
                throw new \InvalidArgumentException('value of $afterSort is not 1 or -1 for ascending and descending');
            }

            $completeFields["payload.{$key}"] = $value;
        }

        $completeFields['earliestGet'] = 1;

        //for the main query in get()
        $this->_ensureIndex($completeFields);

        //for the stuck messages query in get()
        $this->_ensureIndex(array('running' => 1, 'resetTimestamp' => 1));
    }

    /**
     * Ensure an index for the count() method.
     *
     * @param array $fields fields in count() call to index in same format as \MongoCollection::ensureIndex()
     * @param bool $includeRunning whether to include the running field in the index
     *
     * @return void
     *
     * @throws \InvalidArgumentException $includeRunning was not a boolean
     * @throws \InvalidArgumentException key in $fields was not a string
     * @throws \InvalidArgumentException value of $fields is not 1 or -1 for ascending and descending
     */
    public function ensureCountIndex(array $fields, $includeRunning)
    {
        if (!is_bool($includeRunning)) {
            throw new \InvalidArgumentException('$includeRunning was not a boolean');
        }

        $completeFields = array();

        if ($includeRunning) {
            $completeFields['running'] = 1;
        }

        foreach ($fields as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('key in $fields was not a string');
            }

            if ($value !== 1 && $value !== -1) {
                throw new \InvalidArgumentException('value of $fields is not 1 or -1 for ascending and descending');
            }

            $completeFields["payload.{$key}"] = $value;
        }

        $this->_ensureIndex($completeFields);
    }

    /**
     * Get a non running message from the queue.
     *
     * @param array $query in same format as \MongoCollection::find() where top level fields do not contain operators.
     * Lower level fields can however. eg: valid {a: {$gt: 1}, "b.c": 3}, invalid {$and: [{...}, {...}]}
     * @param int $runningResetDuration second duration the message can stay unacked before it resets and can be retreived again.
     * @param int $waitDurationInMillis millisecond duration to wait for a message.
     * @param int $pollDurationInMillis millisecond duration to wait between polls.
     *
     * @return array|null the message or null if one is not found
     *
     * @throws \InvalidArgumentException $runningResetDuration, $waitDurationInMillis or $pollDurationInMillis was not an int
     * @throws \InvalidArgumentException key in $query was not a string
     */
    public function get(array $query, $runningResetDuration, $waitDurationInMillis = 3000, $pollDurationInMillis = 200)
    {
        if (!is_int($runningResetDuration)) {
            throw new \InvalidArgumentException('$runningResetDuration was not an int');
        }

        if (!is_int($waitDurationInMillis)) {
            throw new \InvalidArgumentException('$waitDurationInMillis was not an int');
        }

        if (!is_int($pollDurationInMillis)) {
            throw new \InvalidArgumentException('$pollDurationInMillis was not an int');
        }

        if ($pollDurationInMillis < 0) {
            $pollDurationInMillis = 0;
        }

        //reset stuck messages
        $this->_collection->update(
            array('running' => true, 'resetTimestamp' => array('$lte' => new \MongoDate())),
            array('$set' => array('running' => false)),
            array('multiple' => true)
        );

        $completeQuery = array('running' => false);
        foreach ($query as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('key in $query was not a string');
            }

            $completeQuery["payload.{$key}"] = $value;
        }

        $completeQuery['earliestGet'] = array('$lte' => new \MongoDate());

        $resetTimestamp = time() + $runningResetDuration;
        //ints overflow to floats
        if (!is_int($resetTimestamp)) {
            $resetTimestamp = $runningResetDuration > 0 ? self::MONGO_INT32_MAX : 0;
        }

        $update = array('$set' => array('resetTimestamp' => new \MongoDate($resetTimestamp), 'running' => true));
        $fields = array('payload' => 1);
        $options = array('sort' => array('priority' => 1, 'created' => 1));

        //ints overflow to floats, should be fine
        $end = microtime(true) + ($waitDurationInMillis / 1000.0);

        $sleepTime = $pollDurationInMillis * 1000;
        //ints overflow to floats and already checked $pollDurationInMillis was positive
        if (!is_int($sleepTime)) {
            //ignore since testing a giant sleep takes too long
            //@codeCoverageIgnoreStart
            $sleepTime = PHP_INT_MAX;
        }   //@codeCoverageIgnoreEnd

        while (true) {
            $message = $this->_collection->findAndModify($completeQuery, $update, $fields, $options);
            //checking if _id exist because findAndModify doesnt seem to return null when it can't match the query
            if (array_key_exists('_id', $message)) {
                //id on left of union operator so a possible id in payload doesnt wipe it out the generated one
                return array('id' => $message['_id']) + $message['payload'];
            }

            if (microtime(true) >= $end) {
                return null;
            }

            usleep($sleepTime);
        }

        //ignore since always return from the function from the while loop
        //@codeCoverageIgnoreStart
    }
    //@codeCoverageIgnoreEnd

    /**
     * Count queue messages.
     *
     * @param array $query in same format as \MongoCollection::find() where top level fields do not contain operators.
     * Lower level fields can however. eg: valid {a: {$gt: 1}, "b.c": 3}, invalid {$and: [{...}, {...}]}
     * @param bool|null $running query a running message or not or all
     *
     * @return int the count
     *
     * @throws \InvalidArgumentException $running was not null and not a bool
     * @throws \InvalidArgumentException key in $query was not a string
     */
    public function count(array $query, $running = null)
    {
        if ($running !== null && !is_bool($running)) {
            throw new \InvalidArgumentException('$running was not null and not a bool');
        }

        $totalQuery = array();

        if ($running !== null) {
            $totalQuery['running'] = $running;
        }

        foreach ($query as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('key in $query was not a string');
            }

            $totalQuery["payload.{$key}"] = $value;
        }

        return $this->_collection->count($totalQuery);
    }

    /**
     * Acknowledge a message was processed and remove from queue.
     *
     * @param array $message message received from get()
     *
     * @return void
     *
     * @throws \InvalidArgumentException $message does not have a field "id" that is a MongoId
     */
    public function ack(array $message)
    {
        $id = null;
        if (array_key_exists('id', $message)) {
            $id = $message['id'];
        }

        if (!($id instanceof \MongoId)) {
            throw new \InvalidArgumentException('$message does not have a field "id" that is a MongoId');
        }

        $this->_collection->remove(array('_id' => $id));
    }

    /**
     * Atomically acknowledge and send a message to the queue.
     *
     * @param array $message the message to ack received from get()
     * @param array $payload the data to store in the message to send. Data is handled same way as \MongoCollection::insert()
     * @param int $earliestGet earliest unix timestamp the message can be retreived.
     * @param float $priority priority for order out of get(). 0 is higher priority than 1
     *
     * @return void
     *
     * @throws \InvalidArgumentException $message does not have a field "id" that is a MongoId
     * @throws \InvalidArgumentException $earliestGet was not an int
     * @throws \InvalidArgumentException $priority was not a float
     * @throws \InvalidArgumentException $priority is NaN
     */
    public function ackSend(array $message, array $payload, $earliestGet = 0, $priority = 0.0)
    {
        $id = null;
        if (array_key_exists('id', $message)) {
            $id = $message['id'];
        }

        if (!($id instanceof \MongoId)) {
            throw new \InvalidArgumentException('$message does not have a field "id" that is a MongoId');
        }

        if (!is_int($earliestGet)) {
            throw new \InvalidArgumentException('$earliestGet was not an int');
        }

        if (!is_float($priority)) {
            throw new \InvalidArgumentException('$priority was not a float');
        }

        if (is_nan($priority)) {
            throw new \InvalidArgumentException('$priority was NaN');
        }

        if ($earliestGet > self::MONGO_INT32_MAX) {
            $earliestGet = self::MONGO_INT32_MAX;
        } elseif ($earliestGet < 0) {
            $earliestGet = 0;
        }

        $newMessage = array(
            'payload' => $payload,
            'running' => false,
            'resetTimestamp' => new \MongoDate(self::MONGO_INT32_MAX),
            'earliestGet' => new \MongoDate($earliestGet),
            'priority' => $priority,
            'created' => new \MongoDate(),
        );

        //using upsert because if no documents found then the doc was removed (SHOULD ONLY HAPPEN BY SOMEONE MANUALLY) so we can just send
        $this->_collection->update(array('_id' => $id), $newMessage, array('upsert' => true));
    }

    /**
     * Requeue message to the queue. Same as ackSend() with the same message.
     *
     * @param array $message message received from get().
     * @param int $earliestGet earliest unix timestamp the message can be retreived.
     * @param float $priority priority for order out of get(). 0 is higher priority than 1
     *
     * @return void
     *
     * @throws \InvalidArgumentException $message does not have a field "id" that is a MongoId
     * @throws \InvalidArgumentException $earliestGet was not an int
     * @throws \InvalidArgumentException $priority was not a float
     * @throws \InvalidArgumentException priority is NaN
     */
    public function requeue(array $message, $earliestGet = 0, $priority = 0.0)
    {
        $forRequeue = $message;
        unset($forRequeue['id']);
        $this->ackSend($message, $forRequeue, $earliestGet, $priority);
    }

    /**
     * Send a message to the queue.
     *
     * @param array $payload the data to store in the message. Data is handled same way as \MongoCollection::insert()
     * @param int $earliestGet earliest unix timestamp the message can be retreived.
     * @param float $priority priority for order out of get(). 0 is higher priority than 1
     *
     * @return void
     *
     * @throws \InvalidArgumentException $earliestGet was not an int
     * @throws \InvalidArgumentException $priority was not a float
     * @throws \InvalidArgumentException $priority is NaN
     */
    public function send(array $payload, $earliestGet = 0, $priority = 0.0)
    {
        if (!is_int($earliestGet)) {
            throw new \InvalidArgumentException('$earliestGet was not an int');
        }

        if (!is_float($priority)) {
            throw new \InvalidArgumentException('$priority was not a float');
        }

        if (is_nan($priority)) {
            throw new \InvalidArgumentException('$priority was NaN');
        }

        if ($earliestGet > self::MONGO_INT32_MAX) {
            $earliestGet = self::MONGO_INT32_MAX;
        } elseif ($earliestGet < 0) {
            $earliestGet = 0;
        }

        $message = array(
            'payload' => $payload,
            'running' => false,
            'resetTimestamp' => new \MongoDate(self::MONGO_INT32_MAX),
            'earliestGet' => new \MongoDate($earliestGet),
            'priority' => $priority,
            'created' => new \MongoDate(),
        );

        $this->_collection->insert($message);
    }

    /**
     * Ensure index of correct specification and a unique name whether the specification or name already exist or not.
     *
     * @param array $index index to create in same format as \MongoCollection::ensureIndex()
     *
     * @return void
     *
     * @throws \Exception couldnt create index after 5 attempts
     */
    private function _ensureIndex(array $index)
    {
        for ($i = 0; $i < 5; ++$i) {
            for ($name = uniqid(); strlen($name) > 0; $name = substr($name, 0, -1)) {
                //creating an index with same name and different spec does nothing.
                //creating an index with same spec and different name does nothing.
                //so we use any generated name, and then find the right spec after we have called, and just go with that name.

                try {
                    $this->_collection->ensureIndex($index, array('name' => $name, 'background' => true));
                } catch (\MongoCursorException $e) {
                    //this happens when the name was too long, let continue
                }

                foreach ($this->_collection->getIndexInfo() as $existingIndex) {
                    if ($existingIndex['key'] === $index) {
                        return;
                    }
                }
            }
        }

        throw new \Exception('couldnt create index after 5 attempts');
    }
}
