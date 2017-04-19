<?php

/**
 * Defines methods required of a Proximate cache system that are not provided in PHP Cache's
 * cache pool system.
 */

namespace Proximate\Storage;

use Psr\Cache\CacheItemPoolInterface;
use Proximate\Exception\Init as InitException;

abstract class BaseAdapter
{
    use \Proximate\Feature\RequestParser;

    protected $metadata = [];
    protected $cachePool;

    /**
     * Returns the number of items in the cache
     */
    abstract public function countCacheItems();

    /**
     * Returns all of the keys in the cache
     */
    abstract protected function getCacheKeys();

    /**
     * The simplest possible conversion before caching: just passes through with no changes
     *
     * @param string $response
     * @param array $metadata Any metadata relating to this response
     * @return string
     */
    public function convertResponseToCache($response, array $metadata)
    {
        return $response;
    }

    /**
     * The simplest possible conversion upon retrieval, just passes through with no changes
     *
     * @param string $cachedData
     * @return string
     */
    public function convertCacheToResponse($cachedData)
    {
        return $cachedData;
    }

    /**
     * Gets a page's worth of cache keys
     *
     * @param integer $pageNo
     * @param integer $itemsPerPage
     * @return array
     */
    public function getPageOfCacheKeys($pageNo, $itemsPerPage)
    {
        $items = $this->getCacheKeys();
        $page = array_slice($items, ($pageNo - 1) * $itemsPerPage, $itemsPerPage);

        return $page;
    }

    /**
     * Returns a traversable set of cache items
     *
     * @param integer $pageNo
     * @param integer $itemsPerPage
     * @oaram boolean $includeResponse
     * @return array
     */
    public function getPageOfCacheItems($pageNo, $itemsPerPage, $includeResponse = false)
    {
        $keys = $this->getPageOfCacheKeys($pageNo, $itemsPerPage);
        $cacheItems = $this->getCacheItemPoolInterface()->getItems($keys);

        // Convert the array of CacheItems to an array of arrays
        $items = [];
        /* @var $cacheItem \Cache\Adapter\Common\CacheItem */
        foreach ($cacheItems as $cacheItem)
        {
            if ($item = $cacheItem->get())
            {
                if (!$includeResponse)
                {
                    unset($item['response']);
                }
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Looks up a single cache item by its key
     *
     * @todo Is it worth looking at isHit() in case a request is made for a non-existent key?
     *
     * @param string $key
     * @return array
     */
    public function readCacheItem($key)
    {
        $item = $this->getCacheItemPoolInterface()->getItem($key);

        return $item->get();
    }

    /**
     * Requests a cache item to be deleted
     *
     * @param string $key
     */
    public function expireCacheItem($key)
    {
        $this->getCacheItemPoolInterface()->deleteItem($key);
    }

    /**
     * Creates a cache key for page metadata
     *
     * This can easily be overridden in the child class for more complex matching policies.
     * Note that encrypted connections will be presented as a plaintext proxy request, as
     * a result of the request modification done to make it recordable. Hence $realUrl is
     * used to contain the URL before the CONNECT is modified to a GET.
     *
     * @param string $request
     * @param string $realUrl Usually will match the request header, except for HTTPS URLs
     */
    public function createCacheKey($request, $realUrl)
    {
        $method = $this->getMethodFromProxyRequest($request);
        $body = ($method == 'POST') ?
            $this->getBodyFromRequest($request) :
            '';

        return sha1($method . $body . $realUrl);
    }

    /**
     * Fluent setter to set the cache pool
     *
     * (This could go in a ctor, but it's not needed for everything, so I'd rather use a getter
     * that blows up on failure).
     *
     * @param CacheItemPoolInterface $cachePool
     * @return $this
     */
    public function setCacheItemPoolInterface(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;

        return $this;
    }

    protected function getCacheItemPoolInterface()
    {
        if (!$this->cachePool)
        {
            throw new InitException(
                "Cache pool not set on this CacheAdapter"
            );
        }

        return $this->cachePool;
    }
}
