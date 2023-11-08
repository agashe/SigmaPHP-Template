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
}
