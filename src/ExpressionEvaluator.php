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
     * @param array &$data
     * @return mixed
     */
    public static function execute($expression, &$data)
    {
        // check is the expression is safe.        
        $expressionFiltered = preg_replace([
            '~([\"|\'\`]+)(.*?)(\1)~',
            '~\$([a-zA-Z0-9_]+)~'
        ], '', $expression);
        
        // we check here using the regex "b" option to make sure in the edge 
        // cases such "as" that we gonna search for the exact word , not 
        // partially , like if you have a variable called $last , the expression
        // evaluator will consider it invalid since it has "as" inside :D
        $pattern = '/\b(' . 
            implode('|', array_map('preg_quote', self::$blackList)) . 
            ')\b/i';

        if (preg_match($pattern, $expressionFiltered) === 1) {
            throw new InvalidExpressionException(
                "Invalid expression : ({$expression})"
            );
        }

        extract($data);

        // check if all variables are defined , since eval() will only through
        // Warning if the variable is not defined
        $matches = [];
        preg_match_all('~\$([a-zA-Z0-9_]+)~', $expression, $matches);

        foreach ($matches[1] as $match) {
            if (!isset(${$match})) {
                // check if the var is not wrapped within isset() or empty()
                if (strpos($expression, "isset($$match)") === false &&
                    strpos($expression, "empty($$match)") === false
                ) {
                    throw new UndefinedVariableException(
                        "Undefined variable : $$match"
                    );
                }
            }
        }

        // if the expression is just assigning value to variable
        // don't return anything , just assign the value , also
        // we check that the expression won't consider something
        // like $x == $y an assign :)
        if (preg_match(
            '~^\s*\$([a-zA-Z0-9_]+)\s*=\s*(.*)\s*$~', $expression, $match) &&
            $match[2][0] != '='
        ) {
            eval("\$data['{$match[1]}'] = {$match[2]};");
            return '';
        }

        $result = '';
        eval("\$result = $expression;");
        
        return $result;
    }

    /**
     * Execute all expressions in a line and return the result.
     * 
     * @param string $line
     * @param array &$data
     * @return mixed
     */
    final public static function executeLine($line, &$data)
    {
        $lineExpressions = explode('{{', $line);
        unset($lineExpressions[0]);

        $lineExpressions = array_map(function ($expression) {
            return '{{' . $expression;
        }, $lineExpressions);
        
        foreach ($lineExpressions as $lineExpression) {
            preg_match('~{{(.*?)}}~', $lineExpression, $matchExpression);

            if (empty($matchExpression[0])) {
                continue;
            }

            if (empty($matchExpression[1])) {
                throw new InvalidExpressionException(
                    "Can't process empty expression on line : [{$line}]"
                );
            }

            $expression = trim(str_replace(['{{', '}}'], '', 
                $matchExpression[0]));
            
            $result = self::execute($expression, $data);

            $line = str_replace(
                $matchExpression[0],
                $result,
                $line
            );
        }

        return $line;
    }
}