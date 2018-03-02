<?php

namespace TraderInteractive\Mongo;

use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TraderInteractive\Mongo\Queue
 * @covers ::<private>
 */
final class QueueTest extends TestCase
{
    private $collection;
    private $mongoUrl;
    private $queue;

    public function setUp()
    {
        $this->mongoUrl = getenv('TESTING_MONGO_URL') ?: 'mongodb://localhost:27017';
        $mongo = new \MongoDB\Client(
            $this->mongoUrl,
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );
        $this->collection = $mongo->selectDatabase('testing')->selectCollection('messages');
        $this->collection->drop();

        $this->queue = new Queue($this->mongoUrl, 'testing', 'messages');
    }

    /**
     * @test
     * @covers ::__construct
     * @expectedException \InvalidArgumentException
     */
    public function constructWithNonStringUrl()
    {
        new Queue(1, 'testing', 'messages');
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     */
    public function ensureGetIndex()
    {
        $this->queue->ensureGetIndex(['type' => 1], ['boo' => -1]);
        $this->queue->ensureGetIndex(['another.sub' => 1]);

        $indexes = iterator_to_array($this->collection->listIndexes());
        $this->assertSame(3, count($indexes));

        $expectedOne = [
            'earliestGet' => 1,
            'payload.type' => 1,
            'priority' => 1,
            'created' => 1,
            'payload.boo' => -1,
        ];
        $this->assertSame($expectedOne, $indexes[1]['key']);

        $expectedTwo = [
            'earliestGet' => 1,
            'payload.another.sub' => 1,
            'priority' => 1,
            'created' => 1,
        ];
        $this->assertSame($expectedTwo, $indexes[2]['key']);
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \Exception
     */
    public function ensureGetIndexWithTooLongCollectionName()
    {
        $collectionName = 'messages012345678901234567890123456789012345678901234567890123456789';
        $collectionName .= '012345678901234567890123456789012345678901234567890123456789';//128 chars

        $queue = new Queue($this->mongoUrl, 'testing', $collectionName);
        $queue->ensureGetIndex([]);
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithNonStringBeforeSortKey()
    {
        $this->queue->ensureGetIndex([0 => 1]);
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithNonStringAfterSortKey()
    {
        $this->queue->ensureGetIndex(['field' => 1], [0 => 1]);
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithBadBeforeSortValue()
    {
        $this->queue->ensureGetIndex(['field' => 'NotAnInt']);
    }

    /**
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureGetIndexWithBadAfterSortValue()
    {
        $this->queue->ensureGetIndex([], ['field' => 'NotAnInt']);
    }

    /**
     * Verifies the behaviour of the Queue when it cannot create an index after 5 attempts.
     *
     * @test
     * @covers ::ensureGetIndex
     * @expectedException \Exception
     * @expectedExceptionMessage couldnt create index after 5 attempts
     */
    public function ensureIndexCannotBeCreatedAfterFiveAttempts()
    {
        $mockCollection = $this->getMockBuilder('\MongoDB\Collection')->disableOriginalConstructor()->getMock();

        $mockCollection->method('listIndexes')->willReturn([]);

        $queue = new Queue($mockCollection);
        $queue->ensureCountIndex(['type' => 1], false);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     */
    public function ensureCountIndex()
    {
        $this->queue->ensureCountIndex(['type' => 1, 'boo' => -1], false);
        $this->queue->ensureCountIndex(['another.sub' => 1], true);

        $indexes = iterator_to_array($this->collection->listIndexes());
        $this->assertSame(3, count($indexes));

        $expectedOne = ['payload.type' => 1, 'payload.boo' => -1];
        $this->assertSame($expectedOne, $indexes[1]['key']);

        $expectedTwo = ['earliestGet' => 1, 'payload.another.sub' => 1];
        $this->assertSame($expectedTwo, $indexes[2]['key']);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     */
    public function ensureCountIndexWithPrefixOfPrevious()
    {
        $this->queue->ensureCountIndex(['type' => 1, 'boo' => -1], false);
        $this->queue->ensureCountIndex(['type' => 1], false);

        $indexes = iterator_to_array($this->collection->listIndexes());
        $this->assertSame(2, count($indexes));

        $expected = ['payload.type' => 1, 'payload.boo' => -1];
        $this->assertSame($expected, $indexes[1]['key']);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureCountIndexWithNonStringKey()
    {
        $this->queue->ensureCountIndex([0 => 1], false);
    }

    /**
     * @test
     * @covers ::ensureCountIndex
     * @expectedException \InvalidArgumentException
     */
    public function ensureCountIndexWithBadValue()
    {
        $this->queue->ensureCountIndex(['field' => 'NotAnInt'], false);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getByBadQuery()
    {
        $this->queue->send(['key1' => 0, 'key2' => true]);

        $result = $this->queue->get(['key3' => 0], PHP_INT_MAX, 0);
        $this->assertNull($result);

        $this->assertSame(1, $this->collection->count());
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithNegativePollDuration()
    {
        $this->queue->send(['key1' => 0]);
        $this->assertNotNull($this->queue->get([], 0, 0, -1));
    }

    /**
     * @test
     * @covers ::get
     * @expectedException \InvalidArgumentException
     */
    public function getWithNonStringKey()
    {
        $this->queue->get([0 => 'a value'], 0);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getByFullQuery()
    {
        $messageOne = ['id' => 'SHOULD BE REMOVED', 'key1' => 0, 'key2' => true];

        $this->queue->send($messageOne);
        $this->queue->send(['key' => 'value']);

        $result = $this->queue->get($messageOne, PHP_INT_MAX, 0);

        $this->assertNotSame($messageOne['id'], $result['id']);

        $messageOne['id'] = $result['id'];
        $this->assertSame($messageOne, $result);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getBySubDocQuery()
    {
        $messageTwo = [
            'one' => [
                'two' => [
                    'three' => 5,
                    'notused' => 'notused',
                ],
                'notused' => 'notused',
            ],
            'notused' => 'notused',
        ];

        $this->queue->send(['key1' => 0, 'key2' => true]);
        $this->queue->send($messageTwo);

        $result = $this->queue->get(['one.two.three' => ['$gt' => 4]], PHP_INT_MAX, 0);
        $this->assertSame(['id' => $result['id']] + $messageTwo, $result);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getBeforeAck()
    {
        $messageOne = ['key1' => 0, 'key2' => true];

        $this->queue->send($messageOne);
        $this->queue->send(['key' => 'value']);

        $this->queue->get($messageOne, PHP_INT_MAX, 0);

        //try get message we already have before ack
        $result = $this->queue->get($messageOne, PHP_INT_MAX, 0);
        $this->assertNull($result);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithCustomPriority()
    {
        $messageOne = ['key' => 0];
        $messageTwo = ['key' => 1];
        $messageThree = ['key' => 2];

        $this->queue->send($messageOne, 0, 0.5);
        $this->queue->send($messageTwo, 0, 0.4);
        $this->queue->send($messageThree, 0, 0.3);

        $resultOne = $this->queue->get([], PHP_INT_MAX, 0);
        $resultTwo = $this->queue->get([], PHP_INT_MAX, 0);
        $resultThree = $this->queue->get([], PHP_INT_MAX, 0);

        $this->assertSame(['id' => $resultOne['id']] + $messageThree, $resultOne);
        $this->assertSame(['id' => $resultTwo['id']] + $messageTwo, $resultTwo);
        $this->assertSame(['id' => $resultThree['id']] + $messageOne, $resultThree);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithTimeBasedPriority()
    {
        $messageOne = ['key' => 0];
        $messageTwo = ['key' => 1];
        $messageThree = ['key' => 2];

        $this->queue->send($messageOne);
        $this->queue->send($messageTwo);
        $this->queue->send($messageThree);

        $resultOne = $this->queue->get([], PHP_INT_MAX, 0);
        $resultTwo = $this->queue->get([], PHP_INT_MAX, 0);
        $resultThree = $this->queue->get([], PHP_INT_MAX, 0);

        $this->assertSame(['id' => $resultOne['id']] + $messageOne, $resultOne);
        $this->assertSame(['id' => $resultTwo['id']] + $messageTwo, $resultTwo);
        $this->assertSame(['id' => $resultThree['id']] + $messageThree, $resultThree);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithTimeBasedPriorityWithOldTimestamp()
    {
        $messageOne = ['key' => 0];
        $messageTwo = ['key' => 1];
        $messageThree = ['key' => 2];

        $this->queue->send($messageOne);
        $this->queue->send($messageTwo);
        $this->queue->send($messageThree);

        $resultTwo = $this->queue->get([], PHP_INT_MAX, 0);
        //ensuring using old timestamp shouldn't affect normal time order of send()s
        $this->queue->requeue($resultTwo, 0, 0.0, false);

        $resultOne = $this->queue->get([], PHP_INT_MAX, 0);
        $resultTwo = $this->queue->get([], PHP_INT_MAX, 0);
        $resultThree = $this->queue->get([], PHP_INT_MAX, 0);

        $this->assertSame(['id' => $resultOne['id']] + $messageOne, $resultOne);
        $this->assertSame(['id' => $resultTwo['id']] + $messageTwo, $resultTwo);
        $this->assertSame(['id' => $resultThree['id']] + $messageThree, $resultThree);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWait()
    {
        $start = microtime(true);

        $this->queue->get([], PHP_INT_MAX, 200);

        $end = microtime(true);

        $this->assertTrue($end - $start >= 0.200);
        $this->assertTrue($end - $start < 0.300);
    }

    /**
     * @test
     * @covers ::get
     */
    public function earliestGet()
    {
         $messageOne = ['key1' => 0, 'key2' => true];

         $this->queue->send($messageOne, time() + 1);

         $this->assertNull($this->queue->get($messageOne, PHP_INT_MAX, 0));

         sleep(1);

         $this->assertNotNull($this->queue->get($messageOne, PHP_INT_MAX, 0));
    }

    /**
     * @test
     * @covers ::get
     */
    public function resetStuck()
    {
        $messageOne = ['key' => 0];
        $messageTwo = ['key' => 1];

        $this->queue->send($messageOne);
        $this->queue->send($messageTwo);

        //sets to running
        $this->collection->updateOne(
            ['payload.key' => 0],
            ['$set' => ['earliestGet' => new \MongoDB\BSON\UTCDateTime(time() * 1000)]]
        );
        $this->collection->updateOne(
            ['payload.key' => 1],
            ['$set' => ['earliestGet' => new \MongoDB\BSON\UTCDateTime(time() * 1000)]]
        );

        $this->assertSame(
            2,
            $this->collection->count(
                ['earliestGet' => ['$lte' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000))]]
            )
        );

        //resets and gets messageOne
        $this->assertNotNull($this->queue->get($messageOne, PHP_INT_MAX, 0));

        $this->assertSame(
            1,
            $this->collection->count(
                ['earliestGet' => ['$lte' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000))]]
            )
        );
    }

    /**
     * @test
     * @covers ::count
     * @expectedException \InvalidArgumentException
     */
    public function countWithNonStringKey()
    {
        $this->queue->count([0 => 'a value']);
    }

    /**
     * @test
     * @covers ::count
     */
    public function testCount()
    {
        $message = ['boo' => 'scary'];

        $this->assertSame(0, $this->queue->count($message, true));
        $this->assertSame(0, $this->queue->count($message, false));
        $this->assertSame(0, $this->queue->count($message));

        $this->queue->send($message);
        $this->assertSame(1, $this->queue->count($message, false));
        $this->assertSame(0, $this->queue->count($message, true));
        $this->assertSame(1, $this->queue->count($message));

        $this->queue->get($message, PHP_INT_MAX, 0);
        $this->assertSame(0, $this->queue->count($message, false));
        $this->assertSame(1, $this->queue->count($message, true));
        $this->assertSame(1, $this->queue->count($message));
    }

    /**
     * @test
     * @covers ::ack
     */
    public function ack()
    {
        $messageOne = ['key1' => 0, 'key2' => true];

        $this->queue->send($messageOne);
        $this->queue->send(['key' => 'value']);

        $result = $this->queue->get($messageOne, PHP_INT_MAX, 0);
        $this->assertSame(2, $this->collection->count());

        $this->queue->ack($result);
        $this->assertSame(1, $this->collection->count());
    }

    /**
     * @test
     * @covers ::ack
     * @expectedException \InvalidArgumentException
     */
    public function ackBadArg()
    {
        $this->queue->ack(['id' => new \stdClass()]);
    }

    /**
     * @test
     * @covers ::ackSend
     */
    public function ackSend()
    {
        $messageOne = ['key1' => 0, 'key2' => true];
        $messageThree = ['hi' => 'there', 'rawr' => 2];

        $this->queue->send($messageOne);
        $this->queue->send(['key' => 'value']);

        $resultOne = $this->queue->get($messageOne, PHP_INT_MAX, 0);
        $this->assertSame(2, $this->collection->count());

        $this->queue->ackSend($resultOne, $messageThree);
        $this->assertSame(2, $this->collection->count());

        $actual = $this->queue->get(['hi' => 'there'], PHP_INT_MAX, 0);
        $expected = ['id' => $resultOne['id']] + $messageThree;

        $actual['id'] = $actual['id']->__toString();
        $expected['id'] = $expected['id']->__toString();
        $this->assertSame($expected, $actual);
    }

    /**
     * Verify earliestGet with ackSend.
     *
     * @test
     * @covers ::ackSend
     *
     * @return void
     */
    public function ackSendWithEarliestGet()
    {
        $message = ['key1' => 0, 'key2' => true];
        $this->queue->send($message);
        $result = $this->queue->get([], PHP_INT_MAX, 0);
        $this->assertSame($message['key1'], $result['key1']);
        $this->assertSame($message['key2'], $result['key2']);
        $this->queue->ackSend($result, ['key1' => 1, 'key2' => 2], strtotime('+ 1 day'));
        $actual = $this->queue->get([], PHP_INT_MAX, 0);
        $this->assertNull($actual);
    }

    /**
     * @test
     * @covers ::ackSend
     * @expectedException \InvalidArgumentException
     */
    public function ackSendWithWrongIdType()
    {
        $this->queue->ackSend(['id' => 5], []);
    }

    /**
     * @test
     * @covers ::ackSend
     * @expectedException \InvalidArgumentException
     */
    public function ackSendWithNanPriority()
    {
        $this->queue->ackSend(['id' => new \MongoDB\BSON\ObjectID()], [], 0, NAN);
    }

    /**
     * @test
     * @covers ::ackSend
     */
    public function ackSendWithHighEarliestGet()
    {
        $this->queue->send([]);
        $messageToAck = $this->queue->get([], PHP_INT_MAX, 0);

        $this->queue->ackSend($messageToAck, [], PHP_INT_MAX);

        $expected = [
            'payload' => [],
            'earliestGet' => Queue::MONGO_INT32_MAX,
            'priority' => 0.0,
        ];

        $message = $this->collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->toDateTime()->getTimestamp());
        $this->assertGreaterThan(time() - 10, $message['created']->toDateTime()->getTimestamp());

        unset($message['_id'], $message['created']);
        $message['earliestGet'] = (int)$message['earliestGet']->__toString();

        $this->assertSame($expected, $message);
    }

    /**
     * @covers ::ackSend
     */
    public function ackSendWithLowEarliestGet()
    {
        $this->queue->send([]);
        $messageToAck = $this->queue->get([], PHP_INT_MAX, 0);

        $this->queue->ackSend($messageToAck, [], -1);

        $expected = [
            'payload' => [],
            'running' => false,
            'resetTimestamp' => Queue::MONGO_INT32_MAX,
            'earliestGet' => 0,
            'priority' => 0.0,
        ];

        $message = $this->collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->toDateTime()->getTimestamp());
        $this->assertGreaterThan(time() - 10, $message['created']->toDateTime()->getTimestamp());

        unset($message['_id'], $message['created']);
        $message['resetTimestamp'] = (int)$message['resetTimestamp']->__toString();
        $message['earliestGet'] = (int)$message['earliestGet']->__toString();

        $this->assertSame($expected, $message);
    }

    /**
     * @test
     * @covers ::requeue
     */
    public function requeue()
    {
        $messageOne = ['key1' => 0, 'key2' => true];

        $this->queue->send($messageOne);
        $this->queue->send(['key' => 'value']);

        $resultBeforeRequeue = $this->queue->get($messageOne, PHP_INT_MAX, 0);

        $this->queue->requeue($resultBeforeRequeue);
        $this->assertSame(2, $this->collection->count());

        $resultAfterRequeue = $this->queue->get($messageOne, 0);
        $this->assertSame(['id' => $resultAfterRequeue['id']] + $messageOne, $resultAfterRequeue);
    }

    /**
     * @test
     * @covers ::requeue
     * @expectedException \InvalidArgumentException
     */
    public function requeueBadArg()
    {
        $this->queue->requeue(['id' => new \stdClass()]);
    }

    /**
     * @test
     * @covers ::send
     */
    public function send()
    {
        $payload = ['key1' => 0, 'key2' => true];
        $this->queue->send($payload, 34, 0.8);

        $expected = [
            'payload' => $payload,
            'earliestGet' => 34,
            'priority' => 0.8,
        ];

        $message = $this->collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->toDateTime()->getTimestamp());
        $this->assertGreaterThan(time() - 10, $message['created']->toDateTime()->getTimestamp());

        unset($message['_id'], $message['created']);
        $message['earliestGet'] = $message['earliestGet']->toDateTime()->getTimestamp();

        $this->assertSame($expected, $message);
    }

    /**
     * @test
     * @covers ::send
     * @expectedException \InvalidArgumentException
     */
    public function sendWithNanPriority()
    {
        $this->queue->send([], 0, NAN);
    }

    /**
     * @test
     * @covers ::send
     */
    public function sendWithHighEarliestGet()
    {
        $this->queue->send([], PHP_INT_MAX);

        $expected = [
            'payload' => [],
            'earliestGet' => (new UTCDateTime(Queue::MONGO_INT32_MAX))->toDateTime()->getTimestamp(),
            'priority' => 0.0,
        ];

        $message = $this->collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->toDateTime()->getTimestamp());
        $this->assertGreaterThan(time() - 10, $message['created']->toDateTime()->getTimestamp());

        unset($message['_id'], $message['created']);
        $message['earliestGet'] = $message['earliestGet']->toDateTime()->getTimestamp();

        $this->assertSame($expected, $message);
    }

    /**
     * @test
     * @covers ::send
     */
    public function sendWithLowEarliestGet()
    {
        $this->queue->send([], -1);

        $expected = [
            'payload' => [],
            'earliestGet' => 0,
            'priority' => 0.0,
        ];

        $message = $this->collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->toDateTime()->getTimestamp());
        $this->assertGreaterThan(time() - 10, $message['created']->toDateTime()->getTimestamp());

        unset($message['_id'], $message['created']);
        $message['earliestGet'] = $message['earliestGet']->toDateTime()->getTimestamp();

        $this->assertSame($expected, $message);
    }

    /**
     * Verify Queue can be constructed with \MongoDB\Collection
     *
     * @test
     * @covers ::__construct
     *
     * @return void
     */
    public function constructWithCollection()
    {
        $mongo = new \MongoDB\Client(
            $this->mongoUrl,
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );
        $collection = $mongo->selectDatabase('testing')->selectCollection('custom_collection');
        $collection->drop();
        $queue = new Queue($collection);

        $payload = ['key1' => 0, 'key2' => true];
        $queue->send($payload, 34, 0.8);

        $expected = [
            'payload' => $payload,
            'earliestGet' => 34,
            'priority' => 0.8,
        ];

        $this->assertSame(1, $collection->count());

        $message = $collection->findOne();

        $this->assertLessThanOrEqual(time(), $message['created']->toDateTime()->getTimestamp());
        $this->assertGreaterThan(time() - 10, $message['created']->toDateTime()->getTimestamp());

        unset($message['_id'], $message['created']);
        $message['earliestGet'] = $message['earliestGet']->toDateTime()->getTimestamp();

        $this->assertSame($expected, $message);
    }
}
