<?php

namespace TraderInteractive\Mongo;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TraderInteractive\Mongo\Queue
 * @covers ::<private>
 * @covers ::__construct
 */
final class QueueTest extends TestCase
{
    private $collection;
    private $mongoUrl;
    private $queue;

    public function setUp()
    {
        $this->mongoUrl = getenv('TESTING_MONGO_URL') ?: 'mongodb://localhost:27017';
        $mongo = new Client(
            $this->mongoUrl,
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );
        $this->collection = $mongo->selectDatabase('testing')->selectCollection('messages');
        $this->collection->deleteMany([]);

        $this->queue = new Queue($this->collection);
    }

    public function tearDown()
    {
        (new Client($this->mongoUrl))->dropDatabase('testing');
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
        $collection = (new Client($this->mongoUrl))->selectDatabase('testing')->selectCollection(uniqid());
        $queue = new Queue($collection);
        $queue->ensureGetIndex(['type' => 1], ['boo' => -1]);
        $queue->ensureGetIndex(['another.sub' => 1]);

        $indexes = iterator_to_array($collection->listIndexes());
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
        $collection = (new Client($this->mongoUrl))->selectDatabase('testing')->selectCollection(uniqid());
        $queue = new Queue($collection);
        $queue->ensureCountIndex(['type' => 1, 'boo' => -1], false);
        $queue->ensureCountIndex(['another.sub' => 1], true);

        $indexes = iterator_to_array($collection->listIndexes());
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
        $this->queue->send($this->getMessage(['key1' => 0, 'key2' => true]));

        $result = $this->queue->get(['key3' => 0]);
        $this->assertSame([], $result);

        $this->assertSame(1, $this->collection->count());
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithNegativePollDuration()
    {
        $this->queue->send($this->getMessage(['key1' => 0]));
        $this->assertNotNull($this->queue->get([], ['pollDurationInMillis' => -1]));
    }

    /**
     * @test
     * @covers ::get
     * @expectedException \InvalidArgumentException
     */
    public function getWithNonStringKey()
    {
        $this->queue->get([0 => 'a value']);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getByFullQuery()
    {
        $messageOne = $this->getMessage(['key1' => 0, 'key2' => true]);

        $this->queue->send($messageOne);
        $this->queue->send($this->getMessage(['key' => 'value']));

        $result = $this->queue->get($messageOne->getPayload())[0];

        $this->assertSame((string)$messageOne->getId(), (string)$result->getId());
        $this->assertSame($messageOne->getPayload(), $result->getPayload());
    }

    /**
     * @test
     * @covers ::get
     */
    public function getBySubDocQuery()
    {
        $expected = $this->getMessage(
            [
                'one' => [
                    'two' => [
                        'three' => 5,
                        'notused' => 'notused',
                    ],
                    'notused' => 'notused',
                ],
                'notused' => 'notused',
            ]
        );

        $this->queue->send($this->getMessage(['key1' => 0, 'key2' => true]));
        $this->queue->send($expected);

        $actual = $this->queue->get(['one.two.three' => ['$gt' => 4]])[0];
        $this->assertSameMessage($expected, $actual);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getBeforeAck()
    {
        $messageOne = $this->getMessage(['key1' => 0, 'key2' => true]);

        $this->queue->send($messageOne);
        $this->queue->send($this->getMessage(['key' => 'value']));

        $this->queue->get($messageOne->getPayload());

        //try get message we already have before ack
        $result = $this->queue->get($messageOne->getPayload());
        $this->assertSame([], $result);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithCustomPriority()
    {
        $messageOne = $this->getMessage(['key' => 0], null, 0.5);
        $messageTwo = $this->getMessage(['key' => 1], null, 0.4);
        $messageThree = $this->getMessage(['key' => 2], null, 0.3);

        $this->queue->send($messageOne);
        $this->queue->send($messageTwo);
        $this->queue->send($messageThree);

        $messages = $this->queue->get([], ['maxNumberOfMessages' => 10]);

        $this->assertSameMessage($messageThree, $messages[0]);
        $this->assertSameMessage($messageTwo, $messages[1]);
        $this->assertSameMessage($messageOne, $messages[2]);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWithTimeBasedPriority()
    {
        $messageOne = $this->getMessage(['key' => 0]);
        $messageTwo = $this->getMessage(['key' => 1]);
        $messageThree = $this->getMessage(['key' => 2]);

        $this->queue->send($messageOne);
        $this->queue->send($messageTwo);
        $this->queue->send($messageThree);

        $messages = $this->queue->get([], ['maxNumberOfMessages' => 10]);

        $this->assertSameMessage($messageOne, $messages[0]);
        $this->assertSameMessage($messageTwo, $messages[1]);
        $this->assertSameMessage($messageThree, $messages[2]);
    }

    /**
     * @test
     * @covers ::get
     */
    public function getWait()
    {
        $start = microtime(true);

        $this->queue->get([]);

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
         $messageOne = $this->getMessage(
             ['key1' => 0, 'key2' => true],
             new UTCDateTime((time() + 1) * 1000)
         );

         $this->queue->send($messageOne);

         $this->assertSame([], $this->queue->get($messageOne->getPayload()));

         sleep(1);

         $this->assertCount(1, $this->queue->get($messageOne->getPayload()));
    }

    /**
     * @test
     * @covers ::get
     */
    public function resetStuck()
    {
        $messageOne = $this->getMessage(['key' => 0]);
        $messageTwo = $this->getMessage(['key' => 1]);

        $this->queue->send($messageOne);
        $this->queue->send($messageTwo);

        //sets to running
        $this->collection->updateOne(
            ['payload.key' => 0],
            ['$set' => ['earliestGet' => new UTCDateTime(time() * 1000)]]
        );
        $this->collection->updateOne(
            ['payload.key' => 1],
            ['$set' => ['earliestGet' => new UTCDateTime(time() * 1000)]]
        );

        $this->assertSame(
            2,
            $this->collection->count(
                ['earliestGet' => ['$lte' => new UTCDateTime((int)(microtime(true) * 1000))]]
            )
        );

        //resets and gets messageOne
        $this->assertNotNull($this->queue->get($messageOne->getPayload()));

        $this->assertSame(
            1,
            $this->collection->count(
                ['earliestGet' => ['$lte' => new UTCDateTime((int)(microtime(true) * 1000))]]
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
        $message = $this->getMessage(['boo' => 'scary']);

        $this->assertSame(0, $this->queue->count($message->getPayload(), true));
        $this->assertSame(0, $this->queue->count($message->getPayload(), false));
        $this->assertSame(0, $this->queue->count($message->getPayload()));

        $this->queue->send($message);
        $this->assertSame(1, $this->queue->count($message->getPayload(), false));
        $this->assertSame(0, $this->queue->count($message->getPayload(), true));
        $this->assertSame(1, $this->queue->count($message->getPayload()));

        $this->queue->get($message->getPayload());
        $this->assertSame(0, $this->queue->count($message->getPayload(), false));
        $this->assertSame(1, $this->queue->count($message->getPayload(), true));
        $this->assertSame(1, $this->queue->count($message->getPayload()));
    }

    /**
     * @test
     * @covers ::ack
     */
    public function ack()
    {
        $messageOne = $this->getMessage(['key1' => 0, 'key2' => true]);

        $this->queue->send($messageOne);
        $this->queue->send($this->getMessage(['key' => 'value']));

        $result = $this->queue->get($messageOne->getPayload())[0];
        $this->assertSame(2, $this->collection->count());

        $this->queue->ack($result);
        $this->assertSame(1, $this->collection->count());
    }

    /**
     * @test
     * @covers ::requeue
     */
    public function requeue()
    {
        $messages = [
            $this->getMessage(['id' => 1, 'key' => 'value']),
            $this->getMessage(['id' => 2, 'key' => 'value']),
            $this->getMessage(['id' => 3, 'key' => 'value']),
        ];

        foreach ($messages as $message) {
            $this->queue->send($message);
        }

        $query = ['key' => 'value'];

        $message = $this->queue->get($query)[0];

        $this->assertSameMessage($messages[0], $message);

        $this->queue->requeue($message->withEarliestGet(new UTCDateTime((int)(microtime(true) * 1000))));

        $actual = $this->queue->get([], ['maxNumberOfMessages' => 10]);

        $this->assertSameMessage($messages[1], $actual[0]);
        $this->assertSameMessage($messages[2], $actual[1]);
        $this->assertSameMessage($messages[0], $actual[2]);
    }

    /**
     * @test
     * @covers ::send
     */
    public function send()
    {
        $message = $this->getMessage(['key1' => 0, 'key2' => true], new UTCDateTime(34 * 1000), 0.8);
        $this->queue->send($message);
        $this->assertSingleMessage($message);
    }

    /**
     * @test
     * @covers ::send
     */
    public function sendWithHighEarliestGet()
    {
        $message = $this->getMessage([], new UTCDateTime(PHP_INT_MAX));
        $this->queue->send($message);
        $this->assertSingleMessage($message);
    }

    /**
     * @test
     * @covers ::send
     */
    public function sendWithLowEarliestGet()
    {
        $message = $this->getMessage([], new UTCDateTime(0));
        $this->queue->send($message);
        $this->assertSingleMessage($message);
    }

    private function assertSameMessage(Message $expected, Message $actual)
    {
        $this->assertSame((string)$expected->getId(), (string)$actual->getId());
        $this->assertSame($expected->getPayload(), $actual->getPayload());
        $this->assertSame($expected->getPriority(), $actual->getPriority());
    }

    private function assertSingleMessage(Message $expected)
    {
        $this->assertSame(1, $this->collection->count());

        $actual = $this->collection->findOne(
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );

        $this->assertSame((string)$expected->getId(), (string)$actual['_id']);
        $this->assertSame($expected->getPayload(), $actual['payload']);
        $this->assertSame($expected->getPriority(), $actual['priority']);
        $this->assertEquals($expected->getEarliestGet(), $actual['earliestGet']);
    }

    private function getMessage(array $payload = [], UTCDateTime $earliestGet = null, float $priority = 0.0)
    {
        return new Message(new ObjectId(), $payload, $earliestGet ?? new UTCDateTime(0), $priority);
    }
}
