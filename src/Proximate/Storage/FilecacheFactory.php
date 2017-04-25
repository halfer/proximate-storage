<?php

/**
 * A factory to create class instances to support a file cache
 */

namespace Proximate\Storage;

use League\Flysystem\Adapter\Local as LocalFileAdapter;
use League\Flysystem\Filesystem as FlyFilesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Proximate\Storage\Filesystem as FilesystemCacheAdapter;
use Proximate\Exception\Init as InitException;

class FilecacheFactory
{
    protected $cachePath;
    protected $cachePool;
    protected $cacheAdapter;

    public function __construct($cachePath)
    {
        $this->cachePath = $cachePath;
    }

    public function init()
    {
        // Get the parent dir and the leaf name
        $baseDir = $this->getDirname($this->cachePath);
        $leafDir = $this->getBasename($this->cachePath);

        // This sets up the cache storage system
        $filesystemAdapter = new LocalFileAdapter($baseDir);
        $filesystem = new FlyFilesystem($filesystemAdapter);
        $this->cachePool = new FilesystemCachePool($filesystem, $leafDir);

        // Here is a dependency to perform additional ops on the cache
        $this->cacheAdapter = new FilesystemCacheAdapter($filesystem);
        $this->getCacheAdapter()->setCacheItemPoolInterface($this->getCachePool());
    }

    /**
     * Returns a cache pool
     *
     * @return FilesystemCachePool
     * @throws InitException
     */
    public function getCachePool()
    {
        if (!$this->cachePool)
        {
            throw new InitException("Cache pool not set, have you called init()?");
        }

        return $this->cachePool;
    }

    /**
     * Returns a Proximate cache adapter
     *
     * @return FilesystemCacheAdapter
     * @throws InitException
     */
    public function getCacheAdapter()
    {
        if (!$this->cacheAdapter)
        {
            throw new InitException("Cache adapter not set, have you called init()?");
        }

        return $this->cacheAdapter;
    }

    /**
     * Mockable file method
     *
     * Could replace this with Service\File
     *
     * @param string $path
     * @return string
     */
    protected function getDirname($path)
    {
        return dirname($path);
    }

    /**
     * Mockable file method
     *
     * Could replace this with Service\File
     *
     * @param string $path
     * @return string
     */
    protected function getBasename($path)
    {
        return basename($path);
    }
}
