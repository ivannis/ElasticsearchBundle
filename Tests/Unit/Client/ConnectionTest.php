<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ElasticsearchBundle\Tests\Unit\Client;

use Elasticsearch\Client;
use ONGR\ElasticsearchBundle\Client\Connection;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests if right values are being taken out.
     */
    public function testGetters()
    {
        $config = [
            'index' => 'index_name',
            'body' => [
                'mappings' => [
                    'test_mapping' => [
                        'properties' => [],
                    ],
                ],
            ],
        ];

        $connection = new Connection($this->getClient(), $config);

        $this->assertEquals(
            'index_name',
            $connection->getIndexName(),
            'Recieved wrong index name'
        );
        $this->assertNull(
            $connection->getMapping('product'),
            'should not contain product mapping'
        );
        $this->assertArrayHasKey(
            'properties',
            $connection->getMapping('test_mapping'),
            'should contain test mapping'
        );
    }

    /**
     * Tests drop and create index behaviour.
     */
    public function testDropAndCreateIndex()
    {
        $indices = $this
            ->getMockBuilder('Elasticsearch\Namespaces\IndicesNamespace')
            ->disableOriginalConstructor()
            ->getMock();

        $indices
            ->expects($this->once())
            ->method('create')
            ->with(['index' => 'foo', 'body' => []]);

        $indices
            ->expects($this->once())
            ->method('delete')
            ->with(['index' => 'foo']);

        $client = $this
            ->getMockBuilder('Elasticsearch\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $client
            ->expects($this->exactly(2))
            ->method('indices')
            ->will($this->returnValue($indices));

        $connection = new Connection($client, ['index' => 'foo', 'body' => []]);
        $connection->dropAndCreateIndex();
    }

    /**
     * Tests if scroll request is made properly.
     */
    public function testScroll()
    {
        $client = $this->getClient();
        $client->expects($this->once())
            ->method('scroll')
            ->with(['scroll_id' => 'test_id', 'scroll' => '5m'])
            ->willReturn('test');

        $connection = new Connection($client, ['index' => 'foo', 'body' => []]);
        $result = $connection->scroll('test_id', '5m');

        $this->assertEquals('test', $result);
    }

    /**
     * Tests if exception is thown when unknown operation is recieved.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testBulkException()
    {
        $connection = new Connection($this->getClient(), []);
        $connection->bulk('unknownOperation', 'foo_type', []);
    }

    /**
     * Tests flush method behavior.
     */
    public function testFlush()
    {
        $connection = new Connection($this->getClient(['flush']), []);
        $connection->flush();
    }

    /**
     * Tests refresh method behavior.
     */
    public function testRefresh()
    {
        $connection = new Connection($this->getClient(['refresh']), []);
        $connection->refresh();
    }

    /**
     * Tests if the same client is returned.
     */
    public function testGetClient()
    {
        $client = $this
            ->getMockBuilder('Elasticsearch\Client')
            ->disableOriginalConstructor()
            ->getMock();
        $client
            ->expects($this->never())
            ->method($this->anything());

        $hash = spl_object_hash($client);
        $connection = new Connection($client, []);

        $this->assertEquals($hash, spl_object_hash($connection->getClient()));
    }

    /**
     * Tests setBulkParams method.
     */
    public function testSetBulkParams()
    {
        $client = $this->getClient(['flush']);
        $client->expects($this->once())->method('bulk')->with(['refresh' => 'true']);

        $connection = new Connection($client, []);
        $connection->setBulkParams(['refresh' => 'true']);
        $connection->commit();
    }

    /**
     * Tests forceMapping method.
     */
    public function testForceMapping()
    {
        $connection = new Connection($this->getClient(), []);
        $connection->forceMapping(['product' => []]);
        $this->assertEquals([], $connection->getMapping('product'));
    }

    /**
     * Tests setMapping method.
     */
    public function testSetMapping()
    {
        $connection = new Connection($this->getClient(), []);
        $connection->setMapping('product', ['properties' => []]);
        $this->assertArrayHasKey('properties', $connection->getMapping('product'));
    }

    /**
     * Tests if forcing update settings works as expected.
     */
    public function testUpdateSettingsForce()
    {
        $connection = new Connection(
            $this->getClient(),
            [
                'index' => 'foo',
                'body' => [
                    'mappings' => [
                        'foo' => [
                            'properties' => []
                        ]
                    ]
                ]
            ]
        );

        $this->assertNotEmpty($connection->getMapping('foo'), 'Mapping should exist');

        $connection->updateSettings(['index' => 'foo'], true);

        $this->assertNull($connection->getMapping('foo'), 'Mapping should not exist anymore.');
        $this->assertEquals('foo', $connection->getIndexName(), 'Index name is not correct.');
    }

    /**
     * Returns client instance with indices namespace set.
     *
     * @param array $options
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Client
     */
    private function getClient(array $options = [])
    {
        $indices = $this
            ->getMockBuilder('Elasticsearch\Namespaces\IndicesNamespace')
            ->disableOriginalConstructor()
            ->getMock();
        $indices
            ->expects(in_array('refresh', $options) ? $this->once() : $this->never())
            ->method('refresh');
        $indices
            ->expects(in_array('flush', $options) ? $this->once() : $this->never())
            ->method('flush');

        $client = $this
            ->getMockBuilder('Elasticsearch\Client')
            ->disableOriginalConstructor()
            ->getMock();
        $client
            ->expects($this->any())
            ->method('indices')
            ->will($this->returnValue($indices));

        return $client;
    }
}
