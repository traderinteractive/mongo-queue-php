<?php

namespace TraderInteractive\Mongo;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class Message
{
    private readonly \MongoDB\BSON\ObjectId $id;

    private \MongoDB\BSON\UTCDateTime $earliestGet;

    private float $priority;

    /**
     * Construct a new Message instance.
     *
     * @param ObjectId    $id          The unique id of the message.
     * @param array       $payload     The data to store in the message.
     * @param UTCDateTime $earliestGet The earliest unix timestamp time which the message can be retreived.
     * @param float       $priority    The priority for order out of get(). 0 is higher priority than 1.
     */
    public function __construct(
        ObjectId $id = null,
        private array $payload = [],
        UTCDateTime $earliestGet = null,
        float $priority = 0.0
    ) {
        $this->id = $id ?? new ObjectId();
        $this->earliestGet = $earliestGet ?? new UTCDateTime();
        $this->priority = $this->validatePriority($priority);
    }

    /**
     * Gets the unique id of the message.
     */
    public function getId() : ObjectId
    {
        return $this->id;
    }

    /**
     * Gets the data to store in the message.
     */
    public function getPayload() : array
    {
        return $this->payload;
    }

    /**
     * Gets the earliest unix timestamp time which the message can be retreived.
     */
    public function getEarliestGet() : UTCDateTime
    {
        return $this->earliestGet;
    }

    /**
     * Gets the priority for order out of get(). 0 is higher priority than 1.
     */
    public function getPriority() : float
    {
        return $this->priority;
    }

    /**
     * Create a clone of this message with the data to store in the message.
     *
     * @param array $payload The data to store in the message.
     */
    public function withPayload(array $payload) : Message
    {
        $clone = clone $this;
        $clone->payload = $payload;
        return $clone;
    }

    /**
     * Create a clone of this message with the earliest unix timestamp time which the message can be retreived.
     *
     * @param UTCDateTIme $earliestGet The earliest unix timestamp time which the message can be retreived.
     */
    public function withEarliestGet(UTCDateTime $earliestGet) : Message
    {
        $clone = clone $this;
        $clone->earliestGet = $earliestGet;
        return $clone;
    }

    /**
     * Create a clone of this message with the priority for order out of get(). 0 is higher priority than 1.
     *
     * @param float $priority The priority for order out of get(). 0 is higher priority than 1.
     */
    public function withPriority(float $priority) : Message
    {
        $clone = clone $this;
        $clone->priority = $this->validatePriority($priority);
        return $clone;
    }

    private function validatePriority(float $priority) : float
    {
        if (is_nan($priority)) {
            throw new \InvalidArgumentException('$priority was NaN');
        }

        return $priority;
    }
}
