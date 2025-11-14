<?php

namespace SigmaPHP\Template;

use SigmaPHP\Template\Exceptions\CacheProcessFailedException;
use SigmaPHP\Template\Interfaces\CacheInterface;

/**
 * Cache Class 
 */
class Cache implements CacheInterface
{
    /**
     * @var string $basePath
     */
    private $basePath;

    /**
     * @var string $cachePath
     */
    private $cachePath;

    /**
     * Cache Constructor.
     * 
     * @param string $basePath
     * @param string $cachePath
     */
    public function __construct($basePath, $cachePath)
    {
        $this->basePath = $basePath;
        $this->cachePath = $cachePath;
    }

    /**
     * Get cache file's full path for a template.
     * 
     * @param string $template
     * @param int $modificationTime
     * @return string
     */
    private function getCacheFullPath($template, $modificationTime)
    {
        // cache file naming is very simple:
        // 1- template's name (app , base , dashboard .... etc)
        // 2- modification time of the template file (this will be used later)
        // 3- md5 for 1 and 2
        // 4- subtract the first 30 characters , and this the cache file's name
        return $this->basePath . '/' . 
            $this->cachePath . '/' . 
            substr(md5($template . $modificationTime), 0, 30);
    }
    
    /**
     * Save processed content for template as cache file. 
     * 
     * @param string $template
     * @param int $modificationTime
     * @param string $content
     * @return void
     */
    public function save($template, $modificationTime, $content)
    {
        try {
            file_put_contents(
                $this->getCacheFullPath($template, $modificationTime), 
                $content
            );
        } catch (\Exception $e) {
            throw new CacheProcessFailedException(
                "Can't save cache file for templates"
            );
        }
    }

    /**
     * Load processed content for template from a cache file. 
     * 
     * @param string $template
     * @param int $modificationTime
     * @return string|bool
     */
    public function load($template, $modificationTime)
    {
        $cacheFilePath = $this->getCacheFullPath($template, $modificationTime);

        return $this->validate($template, $modificationTime) ? 
            file_get_contents($cacheFilePath) : false;
    }

    /**
     * Validate cache by comparing time of modification.
     * 
     * @param string $template
     * @param int $modificationTime
     * @return bool
     */
    public function validate($template, $modificationTime)
    {
        return file_exists(
            $this->getCacheFullPath($template, $modificationTime)
        );
    }
}