<?php
namespace TraderInteractiveTest\Mongo;

use PHPUnit\Framework\TestCase;
use TraderInteractive\Mongo\QueueInterface;
use TraderInteractive\Mongo\QueueAwareTrait;

class QueueAwareTraitTest extends TestCase
{
    /**
     * Verify basic behavior of setQueue().
     *
     * @test
     * @covers \TraderInteractive\Mongo\QueueAwareTrait
     *
     * @return void
     */
    public function setQueue(): void
    {
        $mockQueue = $this->getObjectForTrait(QueueAwareTrait::class);
        $mongoQueue = $this->getMockBuilder(QueueInterface::class)->getMock();
        $mockQueue->setQueue($mongoQueue);
        $this->assertEquals($mongoQueue, $mockQueue->getQueue());
    }

    /**
     * Verify basic behavior of getQueue().
     *
     * @test
     * @covers \TraderInteractive\Mongo\QueueAwareTrait
     *
     * @return void
     */
    public function getQueue(): void
    {
        $mockQueue = $this->getObjectForTrait(QueueAwareTrait::class);
        $mongoQueue = $this->getMockBuilder(QueueInterface::class)->getMock();

        $mockQueue->setQueue($mongoQueue);

        $this->assertEquals($mongoQueue, $mockQueue->getQueue());
    }
}
