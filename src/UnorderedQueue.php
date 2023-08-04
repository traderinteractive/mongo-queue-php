<?php
/**
 * Defines the TraderInteractive\Mongo\UnorderedQueue class.
 */

namespace TraderInteractive\Mongo;

use MongoDB\Client;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * Abstraction of mongo db collection as unordered queue.
 */
final class UnorderedQueue extends AbstractQueue implements QueueInterface
{
    /**
     * Override from AbstractQueue class to remove sort options.
     *
     * @var array
     */
    const FIND_ONE_AND_UPDATE_OPTIONS = [
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
    ];

    /**
     * Construct queue.
     *
     * @param \MongoDB\Collection|string $collectionOrUrl A MongoCollection instance or the mongo connection url.
     * @param string $db the mongo db name
     * @param string $collection the collection name to use for the queue
     *
     * @throws \InvalidArgumentException $collectionOrUrl, $db or $collection was not a string
     */
    public function __construct($collectionOrUrl, string $db = null, string $collection = null)
    {
        if ($collectionOrUrl instanceof \MongoDB\Collection) {
            $this->collection = $collectionOrUrl;
            return;
        }

        if (!is_string($collectionOrUrl)) {
            throw new \InvalidArgumentException('$collectionOrUrl was not a string');
        }

        $this->collection = (new Client($collectionOrUrl))->selectDatabase($db)->selectCollection($collection);
    }
}
