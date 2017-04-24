<?php

/**
 * Unit tests for the CacheAdapter\Filesystem class
 */

use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem as FlysystemAdapter;
use Proximate\Storage\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\Common\CacheItem;

class FilesystemTest extends TestCase
{
    protected $flysystem;

    /**
     * Create a mocked flysystem for all tests
     */
    public function setUp()
    {
        $this->flysystem = Mockery::mock(FlysystemAdapter::class);
    }

    public function testCountCacheItems()
    {
        $cacheKeys = $this->getSmallListContents();
        $this->setCacheListExpectation($cacheKeys);
        $this->assertEquals(count($cacheKeys), $this->getCacheAdapter()->countCacheItems());
    }

    public function testGetAllCacheKeys()
    {
        $cacheKeys = $this->getSmallListContents();
        $this->setCacheListExpectation($cacheKeys);
        $this->assertEquals(
            [1, 2, 3, ],
            $this->getCacheAdapter()->getPageOfCacheKeys(1, 10)
        );
    }

    protected function getSmallListContents()
    {
        return $this->getListContents(3);
    }

    /**
     * Tests that pagination works
     *
     * @dataProvider paginationDataProvider
     * @param integer $page
     * @param array $expectedResult
     */
    public function testPagination($page, array $expectedResult)
    {
        $cacheKeys = $this->getLargeListContents();
        $this->setCacheListExpectation($cacheKeys);
        $this->assertEquals(
            $expectedResult,
            $this->getCacheAdapter()->getPageOfCacheKeys($page, 3)
        );
    }

    public function testGetCacheItemsWithResponse()
    {
        $expectedResult = [
            ['response' => 'Item 1', 'url' => 'proto://hello1', ],
            ['response' => 'Item 2', 'url' => 'proto://hello2', ],
            ['response' => 'Item 3', 'url' => 'proto://hello3', ],
        ];
        $this->checkGetCacheItems(true, $expectedResult);
    }

    public function testGetCacheItemsWithoutResponse()
    {
        $expectedResult = [
            ['url' => 'proto://hello1', ],
            ['url' => 'proto://hello2', ],
            ['url' => 'proto://hello3', ],
        ];
        $this->checkGetCacheItems(false, $expectedResult);
    }

    protected function checkGetCacheItems($withResponse, array $expectedResult)
    {
        $cacheKeys = $this->getSmallListContents();
        $this->setCacheListExpectation($cacheKeys);

        // Set up mock for cache pool
        $cacheItems = [
            ['response' => 'Item 1', 'url' => 'proto://hello1', ],
            ['response' => 'Item 2', 'url' => 'proto://hello2', ],
            ['response' => 'Item 3', 'url' => 'proto://hello3', ],
        ];
        $cachePool = $this->getMockedCache();
        $cachePool->
            shouldReceive('getItems')->
            with([1, 2, 3, ])->
            andReturn($this->convertArrayToCacheItems($cacheItems));

        $result = $this->
            getCacheAdapter()->
            setCacheItemPoolInterface($cachePool)->
            getPageOfCacheItems(1, count($cacheItems), $withResponse);
        $this->assertEquals($expectedResult, $result);
    }

    protected function convertArrayToCacheItems($list)
    {
        $array = [];
        foreach ($list as $listItem)
        {
            $cacheItem = Mockery::mock(CacheItem::class);
            $cacheItem->
                shouldReceive('get')->
                andReturn($listItem);
            $array[] = $cacheItem;
        }

        return $array;
    }

    public function paginationDataProvider()
    {
        return [
            [1, [1, 2, 3, ]], // Test first page
            [2, [4, 5, 6, ]], // Test subsequent page
            [3, [7, ]],       // Test a partial page
        ];
    }

    protected function getLargeListContents() // rename to list contents
    {
        return $this->getListContents(7);
    }

    public function testConvertResponseToCache()
    {
        $response = "This is a response";
        $metadata = $this->getDemoMetadata();
        $converted = $this->getCacheAdapter()->convertResponseToCache($response, $metadata);

        $expected = array_merge($metadata, ['response' => $response]);
        $this->assertEquals($expected, $converted);
    }

    /**
     * Responses with missing metadata data
     *
     * @dataProvider missingMetadataKeyDataProvider
     * @expectedException Proximate\Exception\Server
     */
    public function testBadConvertResponseToCache($missingKey)
    {
        $response = "This is a response";
        $metadata = $this->getDemoMetadata();

        // Emulate this key not being set, so an error is thrown
        unset($metadata[$missingKey]);

        $this->getCacheAdapter()->convertResponseToCache($response, $metadata);
    }

    protected function getDemoMetadata()
    {
        return [
            'url' => 'http://example.com/page',
            'method' => 'GET',
            'key' => 'mykey',
        ];
    }

    public function missingMetadataKeyDataProvider()
    {
        return [
            ['url'],
            ['method'],
            ['key'],
        ];
    }

    public function testConvertCacheToResponse()
    {
        $response = "This is a response";
        $metadata = $this->getDemoMetadata();
        $cachedData = array_merge(
            $metadata,
            ['response' => $response, ]
        );

        $converted = $this->getCacheAdapter()->convertCacheToResponse($cachedData);
        $this->assertEquals($response, $converted);
    }

    public function testReadCacheItem()
    {
        $key = 'Key A';
        $valueExpected = ['Cache Item A'];

        // Mock the cache item
        $cacheItem = Mockery::mock(CacheItem::class);
        $cacheItem->
            shouldReceive('get')->
            once()->
            andReturn($valueExpected);

        // Mock the cache pool
        $cachePool = $this->getMockedCache();
        $cachePool->
            shouldReceive('getItem')->
            once()->
            with($key)->
            andReturn($cacheItem);

        $cacheItemResult = $this->
            getCacheAdapter()->
            setCacheItemPoolInterface($cachePool)->
            readCacheItem($key);
        $this->assertEquals($valueExpected, $cacheItemResult);
    }

    public function testExpireCacheItem()
    {
        $key = 'Key B';

        // Mock the cache pool
        $cachePool = $this->getMockedCache();
        $cachePool->
            shouldReceive('deleteItem')->
            once()->
            with($key);

        $this->
            getCacheAdapter()->
            setCacheItemPoolInterface($cachePool)->
            expireCacheItem($key);

        // Dummy test to keep PHPUnit quiet, the once() is the real test
        $this->assertEquals(1, 1);
    }

    protected function getCacheAdapter()
    {
        return new Filesystem($this->getMockedFlysystem());
    }

    protected function setCacheListExpectation(array $cacheKeys)
    {
        $this->
            getMockedFlysystem()->
            shouldReceive('listContents')->
            withNoArgs()->
            andReturn($cacheKeys);
    }

    protected function getListContents($limit)
    {
        $listContents = [];
        for($i = 1; $i <= $limit; $i++)
        {
            $listContents[] = $this->dummyKey($i);
        }

        return $listContents;
    }

    protected function dummyKey($hash)
    {
        return [
            'type' => 'file',
            'path' => 'cache/' . $hash,
            'timestamp' => 1491643670,
            'size' => 15286,
            'dirname' => 'cache',
            'basename' => $hash,
            'filename' => $hash,
        ];
    }

    protected function getMockedFlysystem()
    {
        return $this->flysystem;
    }

    protected function getMockedCache()
    {
        return Mockery::mock(FilesystemCachePool::class);
    }
}
