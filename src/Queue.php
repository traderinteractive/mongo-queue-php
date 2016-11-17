<?php
/**
 * Defines the DominionEnterprises\Mongo\Queue class.
 */

namespace DominionEnterprises\Mongo;

/**
 * Abstraction of mongo db collection as priority queue.
 *
 * Tied priorities are ordered by time. So you may use a single priority for normal queuing (default args exist for
 * this purpose).  Using a random priority achieves random get()
 */
final class Queue extends AbstractQueue implements QueueInterface
{

    /**
     * Construct queue.
     *
     * @param \MongoDB\Collection|string $collectionOrUrl A MongoCollection instance or the mongo connection url.
     * @param string $db the mongo db name
     * @param string $collection the collection name to use for the queue
     *
     * @throws \InvalidArgumentException $collectionOrUrl, $db or $collection was not a string
     */
    public function __construct($collectionOrUrl, $db = null, $collection = null)
    {
        if ($collectionOrUrl instanceof \MongoDB\Collection) {
            $this->collection = $collectionOrUrl;
            return;
        }

        if (!is_string($collectionOrUrl)) {
            throw new \InvalidArgumentException('$collectionOrUrl was not a string');
        }

        if (!is_string($db)) {
            throw new \InvalidArgumentException('$db was not a string');
        }

        if (!is_string($collection)) {
            throw new \InvalidArgumentException('$collection was not a string');
        }

        $mongo = new \MongoDB\Client($collectionOrUrl, [], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
        $mongoDb = $mongo->selectDatabase($db);
        $this->collection = $mongoDb->selectCollection($collection);
    }
}
