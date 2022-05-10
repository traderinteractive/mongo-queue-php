<?php

namespace TraderInteractive\Mongo;

/**
 * Interface for QueueInterface dependency injection.
 */
interface QueueAwareInterface
{
    /**
     * Returns the QueueInterface instance.
     */
    public function getQueue() : QueueInterface;

    /**
     * Sets the QueueInterface instance.
     *
     * @param QueueInterface $queue
     * @return QueueAwareInterface
     */
    public function setQueue(QueueInterface $queue): QueueAwareInterface;
}
