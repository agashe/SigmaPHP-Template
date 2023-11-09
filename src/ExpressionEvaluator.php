<?php

namespace SigmaPHP\Template;

use SigmaPHP\Template\Interfaces\ExpressionEvaluatorInterface;

/**
 * Expression Evaluator Class 
 */
class ExpressionEvaluator implements ExpressionEvaluatorInterface
{
    /**
     * @var array $blackList
     * List by commands that MUST NOT be evaluated. 
     * 
     * Source : https://www.php.net/manual/en/reserved.keywords.php
     */
    private static $blackList = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 
        'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 
        'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 
        'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile',
        'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 
        'global', 'goto', 'if', 'implements', 'include', 'include_once', 
        'instanceof', 'insteadof', 'interface', 'list', 'namespace',
        'new', 'or', 'print', 'private', 'protected', 'public', 'require',
        'require_once', 'return', 'static', 'switch', 'throw', 'trait',
        'try', 'unset', 'use', 'var', 'while', 'xor'
    ];

    /**
     * Execute an expression and return the result.
     * 
     * @param string $expression
     * @param array $data
     * @return mixed
     */
    public static function execute($expression, $data = [])
    {
        // check is the expression is safe.
        foreach (self::$blackList as $keyword) {
            preg_match_all('~^' . $keyword . '$~', $expression, $matches);

            if (!empty($matches[0])) {
                throw new \RuntimeException(
                    "Invalid expression : {$expression}"
                );
            }
        }

        $result = '';
        extract($data);
        eval("\$result = $expression;");

        return $result;
    }
}