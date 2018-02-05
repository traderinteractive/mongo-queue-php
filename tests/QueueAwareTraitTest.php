<?php
namespace TraderInteractiveTest\Mongo;

use PHPUnit\Framework\TestCase;

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
    public function setQueue()
    {
        $mockQueue = $this->getObjectForTrait('\TraderInteractive\Mongo\QueueAwareTrait');
        $this->assertAttributeEquals(null, 'mongoQueue', $mockQueue);
        $mongoQueue = $this->getMockBuilder('\\TraderInteractive\\Mongo\\QueueInterface')->getMock();
        $mockQueue->setQueue($mongoQueue);
        $this->assertAttributeEquals($mongoQueue, 'mongoQueue', $mockQueue);
    }

    /**
     * Verify basic behavior of getQueue().
     *
     * @test
     * @covers \TraderInteractive\Mongo\QueueAwareTrait
     *
     * @return void
     */
    public function getQueue()
    {
        $mockQueue = $this->getObjectForTrait('\TraderInteractive\Mongo\QueueAwareTrait');
        $this->assertNull($mockQueue->getQueue());
        $mongoQueue = $this->getMockBuilder('\\TraderInteractive\\Mongo\\QueueInterface')->getMock();
        $mockQueue->setQueue($mongoQueue);
        $this->assertEquals($mongoQueue, $mockQueue->getQueue());
    }
}
