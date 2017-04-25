<?php

/**
 * Simple real-filesystem tests of this storage module
 */

use PHPUnit\Framework\TestCase;
use Proximate\Storage\FilecacheFactory;

class IntegrationTest extends TestCase
{
    public function testFactoryForReal()
    {
        $factory = new FilecacheFactory('/tmp/proximate/storage');
        $factory->init();
        $cacheAdapter = $factory->getCacheAdapter();
        $cachePool = $factory->getCachePool();

        $cachePool->set($key = 'key1234', $value = 5678);
        $retrievedValue = $cachePool->get($key);
        $this->assertEquals($value, $retrievedValue, "Check we can save and retrieve");
        $this->assertEquals(1, $cacheAdapter->countCacheItems(), "Check count is correct");
    }
}
