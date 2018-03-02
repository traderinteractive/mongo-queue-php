<?php
/**
 * Defines the TraderInteractive\Mongo\Queue class.
 */

namespace TraderInteractive\Mongo;

use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;

/**
 * Abstraction of mongo db collection as priority queue.
 *
 * Tied priorities are ordered by time. So you may use a single priority for normal queuing (default args exist for
 * this purpose).  Using a random priority achieves random get()
 */
abstract class AbstractQueue
{
    /**
     * Maximum millisecond value to use for UTCDateTime creation.
     *
     * @var integer
     */
    const MONGO_INT32_MAX = PHP_INT_MAX;

    /**
     * mongo collection to use for queue.
     *
     * @var \MongoDB\Collection
     */
    protected $collection;

    /**
     * Ensure an index for the get() method.
     *
     * @param array $beforeSort Fields in get() call to index before the sort field in same format
     *                          as \MongoDB\Collection::ensureIndex()
     * @param array $afterSort  Fields in get() call to index after the sort field in same format as
     *                          \MongoDB\Collection::ensureIndex()
     *
     * @return void
     *
     * @throws \InvalidArgumentException value of $beforeSort or $afterSort is not 1 or -1 for ascending and descending
     * @throws \InvalidArgumentException key in $beforeSort or $afterSort was not a string
     */
    final public function ensureGetIndex(array $beforeSort = [], array $afterSort = [])
    {
        //using general rule: equality, sort, range or more equality tests in that order for index
        $completeFields = ['earliestGet' => 1];

        $this->verifySort($beforeSort, 'beforeSort', $completeFields);

        $completeFields['priority'] = 1;
        $completeFields['created'] = 1;

        $this->verifySort($afterSort, 'afterSort', $completeFields);

        //for the main query in get()
        $this->ensureIndex($completeFields);
    }

    /**
     * Ensure an index for the count() method.
     * Is a no-op if the generated index is a prefix of an existing one. If you have a similar ensureGetIndex call,
     * call it first.
     *
     * @param array $fields fields in count() call to index in same format as \MongoDB\Collection::createIndex()
     * @param bool $includeRunning whether to include the running field in the index
     *
     * @return void
     *
     * @throws \InvalidArgumentException key in $fields was not a string
     * @throws \InvalidArgumentException value of $fields is not 1 or -1 for ascending and descending
     */
    final public function ensureCountIndex(array $fields, bool $includeRunning)
    {
        $completeFields = [];

        if ($includeRunning) {
            $completeFields['earliestGet'] = 1;
        }

        $this->verifySort($fields, 'fields', $completeFields);

        $this->ensureIndex($completeFields);
    }

    /**
     * Get a non running message from the queue.
     *
     * @param array $query in same format as \MongoDB\Collection::find() where top level fields do not contain
     *                     operators. Lower level fields can however. eg: valid {a: {$gt: 1}, "b.c": 3},
     *                     invalid {$and: [{...}, {...}]}
     * @param int $runningResetDuration second duration the message can stay unacked before it resets and can be
     *                                  retreived again.
     * @param int $waitDurationInMillis millisecond duration to wait for a message.
     * @param int $pollDurationInMillis millisecond duration to wait between polls.
     *
     * @return array|null the message or null if one is not found
     *
     * @throws \InvalidArgumentException key in $query was not a string
     */
    final public function get(
        array $query,
        int $runningResetDuration,
        int $waitDurationInMillis = 3000,
        int $pollDurationInMillis = 200
    ) {

        $completeQuery = $this->buildPayloadQuery(
            ['earliestGet' => ['$lte' => new UTCDateTime((int)(microtime(true) * 1000))]],
            $query
        );

        $resetTimestamp = $this->calcuateResetTimestamp($runningResetDuration);

        $update = ['$set' => ['earliestGet' => new UTCDateTime($resetTimestamp)]];

        //ints overflow to floats, should be fine
        $end = microtime(true) + ($waitDurationInMillis / 1000.0);
        $sleepTime = $this->calculateSleepTime($pollDurationInMillis);

        do {
            $message = [];
            if ($this->tryFindOneAndUpdate($completeQuery, $update, $message)) {
                return $message;
            }

            usleep($sleepTime);
        } while (microtime(true) < $end);

        return null;
    }

    /**
     * Count queue messages.
     *
     * @param array $query in same format as \MongoDB\Collection::find() where top level fields do not contain
     *                     operators. Lower level fields can however. eg: valid {a: {$gt: 1}, "b.c": 3},
     *                     invalid {$and: [{...}, {...}]}
     * @param bool|null $running query a running message or not or all
     *
     * @return int the count
     *
     * @throws \InvalidArgumentException key in $query was not a string
     */
    final public function count(array $query, bool $running = null) : int
    {
        $totalQuery = [];

        if ($running === true || $running === false) {
            $key = $running ? '$gt' : '$lte';
            $totalQuery['earliestGet'] = [$key => new UTCDateTime((int)(microtime(true) * 1000))];
        }

        return $this->collection->count($this->buildPayloadQuery($totalQuery, $query));
    }

    /**
     * Acknowledge a message was processed and remove from queue.
     *
     * @param array $message message received from get()
     *
     * @return void
     *
     * @throws \InvalidArgumentException $message does not have a field "id" that is a MongoDB\BSON\ObjectID
     */
    final public function ack(array $message)
    {
        $id = null;
        if (array_key_exists('id', $message)) {
            $id = $message['id'];
        }

        if (!(is_a($id, 'MongoDB\BSON\ObjectID'))) {
            throw new \InvalidArgumentException('$message does not have a field "id" that is a ObjectID');
        }

        $this->collection->deleteOne(['_id' => $id]);
    }

    /**
     * Atomically acknowledge and send a message to the queue.
     *
     * @param array $message the message to ack received from get()
     * @param array $payload the data to store in the message to send. Data is handled same way
     *                       as \MongoDB\Collection::insertOne()
     * @param int $earliestGet earliest unix timestamp the message can be retreived.
     * @param float $priority priority for order out of get(). 0 is higher priority than 1
     * @param bool $newTimestamp true to give the payload a new timestamp or false to use given message timestamp
     *
     * @return void
     *
     * @throws \InvalidArgumentException $message does not have a field "id" that is a ObjectID
     * @throws \InvalidArgumentException $priority is NaN
     */
    final public function ackSend(
        array $message,
        array $payload,
        int $earliestGet = 0,
        float $priority = 0.0,
        bool $newTimestamp = true
    ) {
        $id = $message['id'] ?? null;

        $this->throwIfTrue(!is_a($id, ObjectID::class), '$message does not have a field "id" that is a ObjectID');
        $this->throwIfTrue(is_nan($priority), '$priority was NaN');

        $toSet = [
            'payload' => $payload,
            'earliestGet' => $this->getEarliestGetAsUTCDateTime($earliestGet),
            'priority' => $priority,
        ];
        if ($newTimestamp) {
            $toSet['created'] = new UTCDateTime((int)(microtime(true) * 1000));
        }

        //using upsert because if no documents found then the doc was removed (SHOULD ONLY HAPPEN BY SOMEONE MANUALLY)
        //so we can just send
        $this->collection->updateOne(['_id' => $id], ['$set' => $toSet], ['upsert' => true]);
    }

    /**
     * Requeue message to the queue. Same as ackSend() with the same message.
     *
     * @param array $message message received from get().
     * @param int $earliestGet earliest unix timestamp the message can be retreived.
     * @param float $priority priority for order out of get(). 0 is higher priority than 1
     * @param bool $newTimestamp true to give the payload a new timestamp or false to use given message timestamp
     *
     * @return void
     *
     * @throws \InvalidArgumentException $message does not have a field "id" that is a ObjectID
     * @throws \InvalidArgumentException priority is NaN
     */
    final public function requeue(
        array $message,
        int $earliestGet = 0,
        float $priority = 0.0,
        bool $newTimestamp = true
    ) {
        $forRequeue = $message;
        unset($forRequeue['id']);
        $this->ackSend($message, $forRequeue, $earliestGet, $priority, $newTimestamp);
    }

    /**
     * Send a message to the queue.
     *
     * @param array $payload the data to store in the message. Data is handled same way
     *                       as \MongoDB\Collection::insertOne()
     * @param int $earliestGet earliest unix timestamp the message can be retreived.
     * @param float $priority priority for order out of get(). 0 is higher priority than 1
     *
     * @return void
     *
     * @throws \InvalidArgumentException $priority is NaN
     */
    final public function send(array $payload, int $earliestGet = 0, float $priority = 0.0)
    {
        if (is_nan($priority)) {
            throw new \InvalidArgumentException('$priority was NaN');
        }

        //Ensure $earliestGet is between 0 and MONGO_INT32_MAX
        $earliestGet = min(max(0, $earliestGet * 1000), self::MONGO_INT32_MAX);

        $message = [
            'payload' => $payload,
            'earliestGet' => new UTCDateTime($earliestGet),
            'priority' => $priority,
            'created' => new UTCDateTime((int)(microtime(true) * 1000)),
        ];

        $this->collection->insertOne($message);
    }

    /**
     * Ensure index of correct specification and a unique name whether the specification or name already exist or not.
     * Will not create index if $index is a prefix of an existing index
     *
     * @param array $index index to create in same format as \MongoDB\Collection::createIndex()
     *
     * @return void
     *
     * @throws \Exception couldnt create index after 5 attempts
     */
    final private function ensureIndex(array $index)
    {
        if ($this->isIndexIncludedInExistingIndex($index)) {
            return;
        }

        for ($i = 0; $i < 5; ++$i) {
            if ($this->tryCreateIndex($index)) {
                return;
            }
        }

        throw new \Exception('couldnt create index after 5 attempts');
        //@codeCoverageIgnoreEnd
    }

    private function buildPayloadQuery(array $initialQuery, array $payloadQuery)
    {
        foreach ($payloadQuery as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('key in $query was not a string');
            }

            $initialQuery["payload.{$key}"] = $value;
        }

        return $initialQuery;
    }

    private function calculateSleepTime(int $pollDurationInMillis) : int
    {
        $pollDurationInMillis = max($pollDurationInMillis, 0);
        $sleepTime = $pollDurationInMillis * 1000;
        //ints overflow to floats and already checked $pollDurationInMillis was positive
        return is_int($sleepTime) ? $sleepTime : PHP_INT_MAX;
    }

    private function calcuateResetTimeStamp(int $runningResetDuration) : int
    {
        $resetTimestamp = time() + $runningResetDuration;
        //ints overflow to floats
        if (!is_int($resetTimestamp)) {
            $resetTimestamp = $runningResetDuration > 0 ? self::MONGO_INT32_MAX : 0;
        }

        return min(max(0, $resetTimestamp * 1000), self::MONGO_INT32_MAX);
    }

    private function tryFindOneAndUpdate(array $query, array $update, array &$queueMessage) : bool
    {
        $findOneAndUpdateOptions = ['sort' => ['priority' => 1, 'created' => 1]];
        $findOneOptions = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];

        $id = $this->getIdFromMessage(
            $this->collection->findOneAndUpdate($query, $update, $findOneAndUpdateOptions)
        );

        if ($id !== null) {
            // findOneAndUpdate does not correctly return result according to typeMap options so just refetch.
            $message = $this->collection->findOne(['_id' => $id], $findOneOptions);
            //id on left of union operator so a possible id in payload doesnt wipe it out the generated one
            $queueMessage = ['id' => $id] + $message['payload'];
            return true;
        }

        return false;
    }

    private function getIdFromMessage($message)
    {
        if (is_array($message)) {
            return array_key_exists('_id', $message) ? $message['_id'] : null;
        }

        if (is_object($message)) {
            return isset($message->_id) ? $message->_id : null;
        }

        return null;
    }

    private function isIndexIncludedInExistingIndex(array $index) : bool
    {
        //if $index is a prefix of any existing index we are good
        foreach ($this->collection->listIndexes() as $existingIndex) {
            $slice = array_slice($existingIndex['key'], 0, count($index), true);
            if ($slice === $index) {
                return true;
            }
        }

        return false;
    }

    private function tryCreateIndex(array $index) : bool
    {
        for ($name = uniqid(); strlen($name) > 0; $name = substr($name, 0, -1)) {
            if ($this->tryCreateNamedIndex($index, $name)) {
                return true;
            }
        }

        return false;
    }

    private function tryCreateNamedIndex(array $index, string $name) : bool
    {
        //creating an index with same name and different spec does nothing.
        //creating an index with same spec and different name does nothing.
        //so we use any generated name, and then find the right spec after we have called,
        //and just go with that name.
        try {
            $this->collection->createIndex($index, ['name' => $name, 'background' => true]);
        } catch (\MongoDB\Exception\Exception $e) {
            //this happens when the name was too long, let continue
        }

        return $this->indexExists($index);
    }

    private function indexExists(array $index) : bool
    {
        foreach ($this->collection->listIndexes() as $existingIndex) {
            if ($existingIndex['key'] === $index) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper method to validate keys and values for the given sort array
     *
     * @param array  $sort             The proposed sort for a mongo index.
     * @param string $label            The name of the variable given to the public ensureXIndex method.
     * @param array  &$completedFields The final index array with payload. prefix added to fields.
     *
     * @return void
     */
    final private function verifySort(array $sort, string $label, array &$completeFields)
    {
        foreach ($sort as $key => $value) {
            $this->throwIfTrue(!is_string($key), "key in \${$label} was not a string");
            $this->throwIfTrue(
                $value !== 1 && $value !== -1,
                "value of \${$label} is not 1 or -1 for ascending and descending"
            );

            $completeFields["payload.{$key}"] = $value;
        }
    }

    private function throwIfTrue(
        bool $condition,
        string $message,
        string $exceptionClass = '\\InvalidArgumentException'
    ) {
        if ($condition === true) {
            $reflectionClass = new \ReflectionClass($exceptionClass);
            throw $reflectionClass->newInstanceArgs([$message]);
        }
    }

    private function getEarliestGetAsUTCDateTime(int $timestamp) : UTCDateTime
    {
        //Ensure $earliestGet is between 0 and MONGO_INT32_MAX
        $earliestGet = min(max(0, $timestamp * 1000), self::MONGO_INT32_MAX);
        return new UTCDateTime($earliestGet);
    }
}
