<?php

namespace TraderInteractive\Mongo;

/**
 * Trait for QueueAwareInterface implementation.
 */
trait QueueAwareTrait
{
    /**
     * The mongoQueue instance.
     *
     * @var QueueInterface
     */
    private $mongoQueue;

    /**
     * Returns the Queue instance.
     *
     * @return QueueInterface
     */
    public function getQueue()
    {
        return $this->mongoQueue;
    }

    /**
     * Sets the Queue instance.
     *
     * @param QueueInterface $mongoQueue
     *
     * @return QueueAwareInterface
     */
    public function setQueue(QueueInterface $mongoQueue)
    {
        $this->mongoQueue = $mongoQueue;
        return $this;
    }
}
