<?php
namespace TraderInteractiveTest\Mongo;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use TraderInteractive\Mongo\Message;

/**
 * @coversDefaultClass TraderInteractive\Mongo\Message
 * @covers ::__construct
 * @covers ::<private>
 */
class MessageTest extends TestCase
{
    /**
     * @test
     * @covers ::getId
     *
     * @return void
     */
    public function getId()
    {
        $id = new ObjectId();
        $message = new Message($id);
        $this->assertSame($id, $message->getId());
    }

    /**
     * @test
     * @covers ::getPayload
     *
     * @return void
     */
    public function getPayload()
    {
        $payload = ['key' => 'value'];
        $message = new Message(null, $payload);
        $this->assertSame($payload, $message->getPayload());
    }

    /**
     * @test
     * @covers ::withPayload
     *
     * @return void
     */
    public function withPayload()
    {
        $payload = ['key' => 'value'];
        $message = new Message();
        $this->assertNotSame($payload, $message->getPayload());
        $this->assertSame($payload, $message->withPayload($payload)->getPayload());
    }

    /**
     * @test
     * @covers ::getEarliestGet
     *
     * @return void
     */
    public function getEarliestGet()
    {
        $earliestGet = new UTCDateTime();
        $message = new Message(null, [], $earliestGet);
        $this->assertSame($earliestGet, $message->getEarliestGet());
    }

    /**
     * @test
     * @covers ::withEarliestGet
     *
     * @return void
     */
    public function withEarliestGet()
    {
        $earliestGet = new UTCDateTime();
        $message = new Message();
        $this->assertNotSame($earliestGet, $message->getEarliestGet());
        $this->assertSame($earliestGet, $message->withEarliestGet($earliestGet)->getEarliestGet());
    }

    /**
     * @test
     * @covers ::getPriority
     *
     * @return void
     */
    public function getPriority()
    {
        $priority = 1.0;
        $message = new Message(null, [], null, $priority);
        $this->assertSame($priority, $message->getPriority());
    }

    /**
     * @test
     * @covers ::withPriority
     *
     * @return void
     */
    public function withPriority()
    {
        $priority = 1.0;
        $message = new Message();
        $this->assertNotSame($priority, $message->getPriority());
        $this->assertSame($priority, $message->withPriority($priority)->getPriority());
    }

    /**
     * @test
     * @covers ::__construct
     *
     *
     *
     * @return void
     */
    public function constructWithNaNPriority()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$priority was NaN');
        new Message(null, [], null, NAN);
    }

    /**
     * @test
     * @covers ::withPriority
     *
     *
     *
     * @return void
     */
    public function withNaNPriority()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$priority was NaN');
        (new Message())->withPriority(NAN);
    }
}
