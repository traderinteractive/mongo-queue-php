<?php
/**
 * Defines the TraderInteractive\Mongo\Queue class.
 */

namespace TraderInteractive\Mongo;

use MongoDB\Client;
use MongoDB\Collection;

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
     * @param Collection|string $collectionOrUrl A `Collection` instance or the mongo connection URL.
     * @param string|null $db The mongo db name.
     * @param string|null $collection The collection name to use for the queue.
     */
    public function __construct(Collection|string $collectionOrUrl, string $db = null, string $collection = null)
    {
        if ($collectionOrUrl instanceof Collection) {
            $this->collection = $collectionOrUrl;

            return;
        }

        $this->collection = (new Client($collectionOrUrl))
            ->selectDatabase($db)
            ->selectCollection($collection);
    }
}
