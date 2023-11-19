<?php

namespace SigmaPHP\Template\Interfaces;

/**
 * Engine Interface
 */
interface EngineInterface
{
    /**
     * Render template.
     * 
     * @param string $template 
     * @param array $data
     * @param bool $print
     * @return string|void
     */
    public function render($template, $data = [], $print = false);

    /**
     * Register custom directive.
     * 
     * @param string $name
     * @param callable $callback
     * @return void
     */
    public function registerCustomDirective($name, $callback);
}
