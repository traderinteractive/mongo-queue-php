<?php

namespace TraderInteractive\Mongo;

/**
 * Abstraction of mongo db collection as priority queue.
 *
 * Tied priorities are ordered by time. So you may use a single priority for normal queuing (default args exist for
 * this purpose).  Using a random priority achieves random get()
 */
interface QueueInterface
{
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
    public function ensureGetIndex(array $beforeSort = [], array $afterSort = []);

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
    public function ensureCountIndex(array $fields, bool $includeRunning);

    /**
     * Get a non running message from the queue.
     *
     * @param array $query in same format as \MongoDB\Collection::find() where top level fields do not contain
     *                     operators.  Lower level fields can however. eg: valid {a: {$gt: 1}, "b.c": 3},
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
    public function get(
        array $query,
        int $runningResetDuration,
        int $waitDurationInMillis = 3000,
        int $pollDurationInMillis = 200,
        int $limit = 1
    ) : array;

    /**
     * Count queue messages.
     *
     * @param array $query in same format as \MongoDB\Collection::find() where top level fields do not contain
     *                     operators.
     * Lower level fields can however. eg: valid {a: {$gt: 1}, "b.c": 3}, invalid {$and: [{...}, {...}]}
     * @param bool|null $running query a running message or not or all
     *
     * @return int the count
     *
     * @throws \InvalidArgumentException key in $query was not a string
     */
    public function count(array $query, bool $running = null) : int;

    /**
     * Acknowledge a message was processed and remove from queue.
     *
     * @param array $message message received from get()
     *
     * @return void
     *
     * @throws \InvalidArgumentException $message does not have a field "id" that is a MongoDB\BSON\ObjectID
     */
    public function ack(array $message);

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
    public function ackSend(
        array $message,
        array $payload,
        int $earliestGet = 0,
        float $priority = 0.0,
        bool $newTimestamp = true
    );

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
    public function requeue(array $message, int $earliestGet = 0, float $priority = 0.0, bool $newTimestamp = true);

    /**
     * Send a message to the queue.
     *
     * @param array $payload the data to store in the message. Data is handled same way as
     *                       \MongoDB\Collection::insertOne()
     * @param int $earliestGet earliest unix timestamp the message can be retreived.
     * @param float $priority priority for order out of get(). 0 is higher priority than 1
     *
     * @return void
     *
     * @throws \InvalidArgumentException $priority is NaN
     */
    public function send(array $payload, int $earliestGet = 0, float $priority = 0.0);
}
