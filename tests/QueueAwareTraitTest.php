<?php
namespace TraderInteractiveTest\Mongo;

class QueueAwareTraitTest extends \PHPUnit_Framework_TestCase
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
