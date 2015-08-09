<?php
namespace DominionEnterprisesTest\Mongo;

class QueueAwareTraitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Verify basic behavior of setQueue().
     *
     * @test
     * @covers \DominionEnterprises\Mongo\QueueAwareTrait
     *
     * @return void
     */
    public function setQueue()
    {
        $mockQueue = $this->getObjectForTrait('\DominionEnterprises\Mongo\QueueAwareTrait');
        $this->assertAttributeEquals(null, 'mongoQueue', $mockQueue);
        $mongoQueue = $this->getMock('\\DominionEnterprises\\Mongo\\QueueInterface');
        $mockQueue->setQueue($mongoQueue);
        $this->assertAttributeEquals($mongoQueue, 'mongoQueue', $mockQueue);
    }

    /**
     * Verify basic behavior of getQueue().
     *
     * @test
     * @covers \DominionEnterprises\Mongo\QueueAwareTrait
     *
     * @return void
     */
    public function getQueue()
    {
        $mockQueue = $this->getObjectForTrait('\DominionEnterprises\Mongo\QueueAwareTrait');
        $this->assertNull($mockQueue->getQueue());
        $mongoQueue = $this->getMock('\\DominionEnterprises\\Mongo\\QueueInterface');
        $mockQueue->setQueue($mongoQueue);
        $this->assertEquals($mongoQueue, $mockQueue->getQueue());
    }
}
