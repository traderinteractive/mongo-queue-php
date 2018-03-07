<?php

namespace TraderInteractive\Mongo;

use MongoDB\BSON\UTCDateTime;

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
     *                     operators. Lower level fields can however. eg: valid {a: {$gt: 1}, "b.c": 3},
     *                     invalid {$and: [{...}, {...}]}
     * @param array $options Associative array of get options.
     *                           runningResetDuration => integer
     *                               The duration (in miiliseconds) that the received messages are hidden from
     *                               subsequent retrieve requests after being retrieved by a get() request.
     *                           waitDurationInMillis => integer
     *                               The duration (in milliseconds) for which the call will wait for a message to
     *                               arrive in the queue before returning. If a message is available, the call will
     *                               return sooner than WaitTimeSeconds.
     *                           pollDurationInMillis => integer
     *                               The millisecond duration to wait between polls.
     *                           maxNumberOfMessages => integer
     *                               The maximum number of messages to return with get(). All of the messages are not
     *                               necessarily returned.
     *
     * @return array Array of messages.
     *
     * @throws \InvalidArgumentException key in $query was not a string
     */
    public function get(array $query, array $options = []) : array;

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
     * @param Message $message message received from get()
     *
     * @return void
     */
    public function ack(Message $message);

    /**
     * Atomically acknowledge and send a message to the queue.
     *
     * @param Message $message message received from get().
     *
     * @return void
     */
    public function requeue(Message $message);

    /**
     * Send a message to the queue.
     *
     * @param Message $message The message to enqueue.
     *
     * @return void
     *
     * @throws \InvalidArgumentException $priority is NaN
     */
    public function send(Message $message);
}
