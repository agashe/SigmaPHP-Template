<?php

namespace SigmaPHP\Template;

use SigmaPHP\Template\Exceptions\InvalidExpressionException;
use SigmaPHP\Template\Exceptions\UndefinedVariableException;
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
        'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 
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
        $expressionFiltered = preg_replace([
            '~([\"|\'\`]+)(.*?)(\1)~',
            '~\$([a-zA-Z0-9_]+)~'
        ], '', $expression);

        foreach (self::$blackList as $keyword) {
            if (strpos($expressionFiltered, $keyword) !== false) {
                throw new InvalidExpressionException(
                    "Invalid expression : ({$expression})"
                );
            }
        }

        extract($data);

        // check if all variables are defined , since eval() will only through
        // Warning if the variable is not defined
        $matches = [];
        preg_match_all('~\$([a-zA-Z0-9_]+)~', $expression, $matches);

        foreach ($matches[1] as $match) {
            if (!isset(${$match})) {
                throw new UndefinedVariableException(
                    "Undefined variable : $$match"
                );
            }
        }

        $result = '';
        eval("\$result = $expression;");
        
        return $result;
    }

    /**
     * Execute all expressions in a line and return the result.
     * 
     * @param string $line
     * @param array $data
     * @return mixed
     */
    final public static function executeLine($line, $data = [])
    {
        $lineExpressions = explode('{{', $line);
        unset($lineExpressions[0]);

        $lineExpressions = array_map(function ($expression) {
            return '{{' . $expression;
        }, $lineExpressions);
        
        foreach ($lineExpressions as $lineExpression) {
            preg_match('~{{[^{}]+}}~', $lineExpression, $matchExpression);
            
            if (empty($matchExpression[0])) {
                continue;
            }

            $expression = trim(str_replace(['{{', '}}'], '', 
                $matchExpression[0]));
            
            // check is the expression is safe.        
            $expressionFiltered = preg_replace([
                '~([\"|\'\`]+)(.*?)(\1)~',
                '~\$([a-zA-Z0-9_]+)~'
            ], '', $expression);
            
            foreach (self::$blackList as $keyword) {
                if (strpos($expressionFiltered, $keyword) !== false) {
                    throw new InvalidExpressionException(
                        "Invalid expression : ({$expression})"
                    );
                }
            }
            
            extract($data);

            // check if all variables are defined , since eval() will only
            // throw (Warning) if the variable is not defined !!
            $matches = [];
            preg_match_all('~\$([a-zA-Z0-9_]+)~', $expression, $matches);

            foreach ($matches[1] as $match) {
                if (!isset(${$match})) {
                    throw new UndefinedVariableException(
                        "Undefined variable : $$match"
                    );
                }
            }

            $result = '';
            eval("\$result = $expression;");
            
            $line = str_replace(
                $matchExpression[0],
                ' ' . $result . ' ',
                $line
            );
        }

        return $line;
    }
}