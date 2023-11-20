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
     * @return mixed
     */
    public static function execute($expression, &$data);

    /**
     * Execute all expressions in a line and return the result.
     * 
     * @param string $line
     * @param array &$data
     * @return mixed
     */
    public static function executeLine($expression, &$data);
}
