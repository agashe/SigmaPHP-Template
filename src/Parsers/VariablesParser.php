<?php

namespace SigmaPHP\Template\Parsers;

use SigmaPHP\Template\Exceptions\TemplateParsingException;
use SigmaPHP\Template\Interfaces\ParserInterface;
use SigmaPHP\Template\ExpressionEvaluator;

/**
 * Variables Parser Class 
 */
class VariablesParser implements ParserInterface
{
    /**
     * @var array $content
     */
    private $content;
    
    /**
     * @var array $data
     */
    private $data;

    /**
     * @var string $template
     */
    public $template;

    /**
     * Variables Parser Constructor.
     */
    public function __construct() {
        $this->template = '';
        $this->content = [];
        $this->data = [];
    }

    /**
     * Define variables on line.
     * 
     * @param string $line
     * @param int $lineNumber
     * @return void
     */
    private function defineVariables($line, $lineNumber)
    {
        // for multiple variables on the same line , we break the line
        // and do our process , then remove the definition tags
        // finally we glue everything back together
        $lineParts = explode('{%', $line);
        
        foreach ($lineParts as $part) {
            if (preg_match(
                '~{% define \$([a-zA-Z0-9_]+)\s*=\s*(.*) %}~',
                '{%' . $part, $match)
            ) {
                $this->data[$match[1]] = ExpressionEvaluator::execute(
                    $match[2],
                    $this->data
                );

                $this->content[$lineNumber] = str_replace(
                    $match[0],
                    '',
                    $this->content[$lineNumber]
                );
            }
        }
    }
    
    /**
     * Handle variables inside nested inline conditions , blocks and loops.
     * 
     * @return void
     */
    private function handleVariablesInsideNestedInlineDirectives()
    {
        foreach ($this->content as $i => $line) {
            // skip the lines that don't contain inline directives
            if (
                !((strpos($line, '{% if') !== false) &&
                 (strpos($line, '{% end_if ') !== false)    
                ) &&
                !((strpos($line, '{% block') !== false) &&
                 (strpos($line, '{% end_block ') !== false)    
                ) &&
                !((strpos($line, '{% for') !== false) &&
                 (strpos($line, '{% end_for') !== false)    
                ) 
            ) {
                continue;
            }

            $lineParts = explode('{%', $line);
            
            // check for variables inside blocks , conditions and loops
            // if found throw exception , otherwise define them
            $isCondition = 0;
            $isBlock = 0;
            $isLoop = 0;
    
            foreach ($lineParts as $part) {
                $part = '{%' . $part;
    
                if (!$isCondition && !$isBlock && !$isLoop) {
                    $this->defineVariables($part, $i);
                }
    
                if (strpos($part, '{% if') !== false) {
                    $isCondition += 1;
                }
                
                if (strpos($part, '{% block') !== false) {
                    $isBlock += 1;
                }
    
    
                if (strpos($part, '{% for') !== false) {
                    $isLoop += 1;
                }
                
                if ($isCondition || $isBlock || $isLoop) {
                    if (preg_match(
                        '~{% define \$([a-zA-Z0-9_]+)\s*=\s*(.*) %}~',
                        $part, $match)
                    ) {
                        throw new TemplateParsingException(
                            "Variable defined inside local scope : [{$part}]" .
                            " , in template [{$this->template}]"
                        );
                    }
                }
    
                if (strpos($part, '{% end_if') !== false) {
                    $isCondition -= 1;
                }
    
                if (strpos($part, '{% end_block') !== false) {
                    $isBlock -= 1;
                }
    
                if (strpos($part, '{% end_for') !== false) {
                    $isLoop -= 1;
                }
            }
        }
    }

    /**
     * Handle variables mixed with directives.
     * 
     * @return void
     */
    private function handleVariablesMixedWithDirectives()
    {   
        // we break each line into 2 part , then we check if after the start tag
        // a variable definition or before the end tag , only in that case we
        // throw exception , otherwise we continue execution
        $directives = [
            'if' , 'for', 'block',
            'end_if' , 'end_for', 'end_block'
        ];

        foreach ($this->content as $i => $line) {
            // skip lines that doesn't have start tag with define
            if ((strpos($line, '{% define') !== false) &&
                (
                    (strpos($line, '{% if') !== false) ||
                    (strpos($line, '{% block') !== false) ||
                    (strpos($line, '{% for') !== false) ||
                    (strpos($line, '{% end_if') !== false) ||
                    (strpos($line, '{% end_block') !== false) ||
                    (strpos($line, '{% end_for') !== false)
                )    
            ) {
                foreach ($directives as $n => $directive) {
                    $lineParts = explode('{% ' . $directive, $line);
                        
                    if (isset($lineParts[1]) && 
                        (strpos($lineParts[((int)($n <= 2))], 
                            '{% define') !== false)
                    ) {
                        throw new TemplateParsingException(
                            "Variable defined inside local scope : [{$line}]" .
                            " , in template [{$this->template}]"
                        );
                    }
                }

                $this->defineVariables($line, $i);
            }
        }
    }

    /**
     * Handle variables inside nested conditions , blocks and loops.
     * 
     * @return void
     */
    private function handleVariablesInsideNestedDirectives()
    {
        // check for variables inside blocks , conditions and loops
        // if found throw exception , otherwise define them
        $isCondition = 0;
        $isBlock = 0;
        $isLoop = 0;

        foreach ($this->content as $i => $line) {
            if ((strpos($line, '{% define') !== false) &&
                (
                    (strpos($line, '{% if') !== false) ||
                    (strpos($line, '{% block') !== false) ||
                    (strpos($line, '{% for') !== false) ||
                    (strpos($line, '{% end_if') !== false) ||
                    (strpos($line, '{% end_block') !== false) ||
                    (strpos($line, '{% end_for') !== false)
                )    
            ) {
                continue;
            }

            if (!$isCondition && !$isBlock && !$isLoop) {
                $this->defineVariables($line, $i);
                $line = $this->content[$i];
            }

            if (strpos($line, '{% define') === false) {
                if (strpos($line, '{% if') !== false) {
                    $isCondition += 1;
                }
                
                if (strpos($line, '{% block') !== false) {
                    $isBlock += 1;
                }


                if (strpos($line, '{% for') !== false) {
                    $isLoop += 1;
                }
            }
            
            if ($isCondition || $isBlock || $isLoop) {
                if (preg_match(
                    '~{% define \$([a-zA-Z0-9_]+)\s*=\s*(.*) %}~',
                    $line, $match)
                ) {
                    throw new TemplateParsingException(
                        "Variable defined inside local scope : [{$line}]" .
                        " , in template [{$this->template}]"
                    );
                }
            }

            if (strpos($line, '{% define') === false) {
                if (($isCondition > 0) && 
                    strpos($line, '{% end_if') !== false
                ) {
                    $isCondition -= 1;
                }

                if (($isBlock > 0) && strpos($line, '{% end_block') !== false) {
                    $isBlock -= 1;
                }

                if (($isLoop > 0) && strpos($line, '{% end_for') !== false) {
                    $isLoop -= 1;
                }
            }
        }
    }
    
    /**
     * Parse variables in a template.
     * 
     * @param array $content
     * @param array &$data
     * @return array
     */
    final public function parse($content, &$data)
    {
        $this->content = $content;
        $this->data = $data;

        $this->handleVariablesInsideNestedInlineDirectives();
        $this->handleVariablesMixedWithDirectives();
        $this->handleVariablesInsideNestedDirectives();

        $data = $this->data;

        return $this->content;
    }
}