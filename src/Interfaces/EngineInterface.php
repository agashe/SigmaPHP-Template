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
     * @return void
     */
    public function render($template, $data = []);

    /**
     * Register custom directive.
     * 
     * @param string $name
     * @param callable $callback
     * @return void
     */
    public function registerCustomDirective($name, $callback);
}
