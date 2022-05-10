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
    private QueueInterface $mongoQueue;

    /**
     * Returns the Queue instance.
     */
    public function getQueue() : QueueInterface
    {
        return $this->mongoQueue;
    }

    /**
     * Sets the Queue instance.
     *
     * @param QueueInterface $mongoQueue
     * @return QueueAwareTrait
     */
    public function setQueue(QueueInterface $mongoQueue): static
    {
        $this->mongoQueue = $mongoQueue;

        return $this;
    }
}
