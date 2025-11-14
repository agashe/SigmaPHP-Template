<?php

namespace SigmaPHP\Template\Interfaces;

/**
 * Cache Interface
 */
interface CacheInterface
{   
    /**
     * Save processed content for template as cache file. 
     * 
     * @param string $template
     * @param int $modificationTime
     * @param string $content
     * @return void
     */
    public function save($template, $modificationTime, $content);

    /**
     * Load processed content for template from a cache file. 
     * 
     * @param string $template
     * @param int $modificationTime
     * @return string|bool
     */
    public function load($template, $modificationTime);

    /**
     * Validate cache by comparing time of modification.
     * 
     * @param string $template
     * @param int $modificationTime
     * @return bool
     */
    public function validate($template, $modificationTime);
}
