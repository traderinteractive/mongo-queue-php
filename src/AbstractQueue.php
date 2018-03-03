<?php
/**
 * Defines the TraderInteractive\Mongo\Queue class.
 */

namespace TraderInteractive\Mongo;

use ArrayObject;
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
     * @param int $limit The maximum number of messages to return.
     *
     * @return array Array of messages.
     *
     * @throws \InvalidArgumentException key in $query was not a string
     */
    final public function get(
        array $query,
        int $runningResetDuration,
        int $waitDurationInMillis = 3000,
        int $pollDurationInMillis = 200,
        int $limit = 1
    ) : array {
        $completeQuery = $this->buildPayloadQuery(
            ['earliestGet' => ['$lte' => new UTCDateTime((int)(microtime(true) * 1000))]],
            $query
        );

        $resetTimestamp = $this->calcuateResetTimestamp($runningResetDuration);

        $update = ['$set' => ['earliestGet' => new UTCDateTime($resetTimestamp)]];

        //ints overflow to floats, should be fine
        $end = microtime(true) + ($waitDurationInMillis / 1000.0);
        $sleepTime = $this->calculateSleepTime($pollDurationInMillis);

        $messages = new ArrayObject();

        while (count($messages) < $limit) {
            if ($this->tryFindOneAndUpdate($completeQuery, $update, $messages)) {
                continue;
            }

            if (microtime(true) < $end) {
                usleep($sleepTime);
            }

            break;
        }

        return $messages->getArrayCopy();
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
     * @param Message $message message received from get()
     *
     * @return void
     */
    final public function ack(Message $message)
    {
        $this->collection->deleteOne(['_id' => $message->getId()]);
    }

    /**
     * Atomically acknowledge and send a message to the queue.
     *
     * @param Message $message message received from get().
     *
     * @return void
     */
    final public function requeue(Message $message)
    {
        $set = [
            'payload' => $message->getPayload(),
            'earliestGet' => $message->getEarliestGet(),
            'priority' => $message->getPriority(),
            'created' => new UTCDateTime(),
        ];

        $this->collection->updateOne(['_id' => $message->getId()], ['$set' => $set], ['upsert' => true]);
    }

    /**
     * Send a message to the queue.
     *
     * @param Message $message The message to send.
     *
     * @return void
     */
    final public function send(Message $message)
    {
        $document = [
            '_id' => $message->getId(),
            'payload' => $message->getPayload(),
            'earliestGet' => $message->getEarliestGet(),
            'priority' => $message->getPriority(),
            'created' => new UTCDateTime(),
        ];

        $this->collection->insertOne($document);
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

    private function tryFindOneAndUpdate(array $query, array $update, ArrayObject $messages) : bool
    {
        $findOneAndUpdateOptions = ['sort' => ['priority' => 1]];
        $findOneOptions = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];

        $id = $this->getIdFromMessage(
            $this->collection->findOneAndUpdate($query, $update, $findOneAndUpdateOptions)
        );

        if ($id !== null) {
            // findOneAndUpdate does not correctly return result according to typeMap options so just refetch.
            $message = $this->collection->findOne(['_id' => $id], $findOneOptions);
            //id on left of union operator so a possible id in payload doesnt wipe it out the generated one
            $messages[] = ['id' => $id] + $message['payload'];
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
}