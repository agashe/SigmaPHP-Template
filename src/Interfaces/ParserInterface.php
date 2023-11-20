<?php

namespace SigmaPHP\Template\Interfaces;

/**
 * Parser Interface
 */
interface ParserInterface
{
    /**
     * Parse specific type of blocks in a template different types
     * of blocks including : conditional, loops and content
     * 
     * @param array $content
     * @param array &$data
     * @return array
     */
    public function parse($content, &$data);
}
