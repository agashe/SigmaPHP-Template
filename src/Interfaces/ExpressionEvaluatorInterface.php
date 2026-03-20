<?php

namespace SigmaPHP\Template\Interfaces;

/**
 * Expression Evaluator Interface
 */
interface ExpressionEvaluatorInterface
{
    /**
     * Execute an expression and return the result.
     *
     * @param string $expression
     * @param array &$data
     * @param string $template
     * @return mixed
     */
    public static function execute($expression, &$data, $template);

    /**
     * Execute all expressions in a line and return the result.
     *
     * @param string $line
     * @param array &$data
     * @param string $template
     * @return mixed
     */
    public static function executeLine($expression, &$data, $template);
}
