<?php

namespace SigmaPHP\Template;

use SigmaPHP\Template\Exceptions\InvalidStatementException;
use SigmaPHP\Template\Exceptions\TemplateNotFoundException;
use SigmaPHP\Template\Exceptions\TemplateParsingException;
use SigmaPHP\Template\Exceptions\UndefinedDirectiveException;
use SigmaPHP\Template\Interfaces\EngineInterface;
use SigmaPHP\Template\ExpressionEvaluator;
use SigmaPHP\Template\Parsers\BlocksParser;
use SigmaPHP\Template\Parsers\ConditionsParser;
use SigmaPHP\Template\Parsers\LoopsParser;

/**
 * Template Engine Class 
 */
class Engine implements EngineInterface
{
    /**
     * Template files extension
     */
    const TEMPLATE_FILE_EXTENSION = 'template.html';

    /**
     * @var string $templatesPath
     * The root path for all template files.
     */
    private $templatesPath;
    
    /**
     * @var string $currentTemplatePath
     * Current template under processing , useful for
     * include and extend by relative path.
     */
    private $currentTemplatePath;

    /**
     * @var string $template
     */
    private $template;

    /**
     * @var array $content
     */
    private $content;

    /**
     * @var array $data
     */
    private $data;

    /**
     * @var \BlocksParser $blocksParser
     */
    private $blocksParser;
    
    /**
     * @var \ConditionsParser $conditionsParser
     */
    private $conditionsParser;
    
    /**
     * @var \LoopsParser $loopsParser
     */
    private $loopsParser;

    /**
     * @var array $customDirectives
     */
    private $customDirectives;

    /**
     * Template Engine Constructor.
     * 
     * @param string $templatesPath
     */
    public function __construct($templatesPath = '') {
        $this->templatesPath = $templatesPath;

        $this->blocksParser = new BlocksParser();
        $this->conditionsParser = new ConditionsParser();
        $this->loopsParser = new LoopsParser();

        $this->customDirectives = [];
    }

    /**
     * Render template.
     * 
     * @param string $template 
     * @param array $data 
     * @return void
     */
    final public function render($template, $data = [])
    {
        // in case the developer used './' with the render method
        // we remove it since it points to the relative base path
        // which in this case the path to templates  
        $template = str_replace('./', '', $template);

        $this->content = $this->getTemplateContent($template);
        $this->data = $data;

        // init parsers
        $this->blocksParser->template = $template;
        $this->conditionsParser->template = $template;
        $this->loopsParser->template = $template;

        $this->blocksParser->blocks = [];
        
        $this->conditionsParser->conditions = [];
        $this->conditionsParser->inlineConditions = [];
        
        $this->loopsParser->loops = [];
        $this->loopsParser->inlineLoops = [];

        // get the current template path
        $this->currentTemplatePath = $this->templatesPath;

        if (strpos($template, '.') !== false) {
            $this->currentTemplatePath = explode('.', $template);
            
            unset($this->currentTemplatePath[
                count($this->currentTemplatePath) - 1
            ]);

            $this->currentTemplatePath = implode('.', 
                $this->currentTemplatePath);
        }

        // prefix template's name
        $this->template = $template . '.' . self::TEMPLATE_FILE_EXTENSION;

        while ($this->processTemplate());
                        
        print implode("\n", $this->content);
    }

    /**
     * Render template.
     * 
     * @param string $name
     * @param callable $callback
     * @return void
     */
    final public function registerCustomDirective($name, $callback)
    {
        $this->customDirectives[$name] = $callback;
    }

    /**
     * Get template file content.
     * 
     * @param string $templateFileName
     * @return array
     */
    private function getTemplateContent($templateFileName)
    {
        // set the current path
        if (strpos($templateFileName, './') !== false) {
            $templateFileName = str_replace(
                './', 
                $this->currentTemplatePath . '.',
                $templateFileName
            );
        }

        // in case the template is in a sub-directory
        // we use dot-notation , but it's just the matter of
        // replace the dots with slashes to get the correct path 
        $templateFileName = str_replace('.', '/', $templateFileName);

        $templateFullPath = $this->templatesPath . '/' .
            $templateFileName . '.' .
            self::TEMPLATE_FILE_EXTENSION;
        
        if (!file_exists($templateFullPath)) {
            throw new TemplateNotFoundException(
                "The requested template [{$templateFileName}.template.html] " .
                "doesn't exist"
            );
        }

        // clean lines break and return the content as array 
        return explode("\n", str_replace(
            ["\n\r", "\r"],
            "\n",
            file_get_contents($templateFullPath)  
        ));
    }

    /**
     * Check if one word from an array exists in a text. 
     * 
     * @param array $phrases
     * @param string $text
     * @return bool
     */
    private function phraseExists($phrases, $text)
    {
        foreach ($phrases as $phrase) {
            if (strpos($text, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove comments block from template.
     * 
     * @return void
     */
    private function removeComments()
    {
        $isComment = false;

        foreach ($this->content as $i => $line) {
            if ((strpos($line, '{--') !== false) &&
                (strpos($line, '--}') !== false)
            ) {
                $lineParts = explode('{--', $line);
                $commentedText = explode('--}', $lineParts[1])[0];

                $this->content[$i] = str_replace(
                    '{--' . $commentedText . '--}',
                    '',
                    $line
                );

                continue;
            }

            if ((!$isComment) && (strpos($line, '{--') !== false)) {
                $isComment = true;

                $lineParts = explode('{--', $line);

                $this->content[$i] = str_replace(
                    '{--' . $lineParts[1],
                    '',
                    $line
                );
            }

            if ($isComment &&
                (strpos($line, '{--') === false) &&
                (strpos($line, '--}') === false)
            ) {
                $this->content[$i] = '';
            }
            
            if ($isComment && (strpos($line, '--}') !== false)) {
                $lineParts = explode('--}', $line);

                $this->content[$i] = str_replace(
                    $lineParts[0] . '--}',
                    '',
                    $line
                );

                $isComment = false;
            }
        }
    }

    /**
     * Clean extra spaces from template lines. 
     * 
     * @return void
     */
    private function cleanTemplate()
    {
        foreach ($this->content as $i => $line) {
            // handle spaces in the tags properly
            $line = preg_replace(['~{%~', '~%}~'], ['{% ', ' %}'], $line);
            $line = preg_replace('/\s+/', ' ', $line);

            // check if the statement is valid
            if (strpos($line, '{%') !== false) {
                // first we check how many command we expect
                preg_match_all('~{%~', $line, $matches);
                $commandsCount = count($matches[0]);

                $validCommands = [
                    '~{% extend ([\"|\']+){1}([a-zA-Z0-9\.\-\_\/]+)(\1) %}~',
                    '~{% include ([\"|\']+){1}([a-zA-Z0-9\.\-\_\/]+)(\1) %}~',
                    '~{% show_block ([\"|\']+){1}([a-zA-Z0-9\.\-\_]+)(\1) %}~',
                    '~{% block ([\"|\']+){1}([a-zA-Z0-9\.\-\_]+)(\1) %}~', 
                    '~{% end_block %}~', 
                    '~{% end_block ([\"|\']+){1}([a-zA-Z0-9\.\-\_]+)(\1) %}~', 
                    '~{% define \$([a-zA-Z0-9_]+)\s*=\s*(.*) %}~',
                    '~{% if \((.*?)\) %}~',
                    '~{% else_if \((.*?)\) %}~',
                    '~{% else %}~',
                    '~{% else ([0-9]+) %}~', 
                    '~{% end_if %}~',
                    '~{% end_if ([0-9]+) %}~', 
                    '~{% for \$([a-zA-Z0-9_]+) in (.*?) %}~',
                    '~{% for \$([a-zA-Z0-9_]+) in (.*?) \(([0-9]+)\) %}~',
                    '~{% break \((.*?)\) %}~',
                    '~{% continue \((.*?)\) %}~',
                    '~{% break \((.*?)\) \<([0-9]+)\> %}~',
                    '~{% continue \((.*?)\) \<([0-9]+)\> %}~',
                    '~{% end_for ([0-9]+) %}~', 
                    '~{% end_for %}~',
                    '~{%\s*([a-zA-Z0-9\_]+)\((.*?)\)\s*%}~'
                ];

                // loop until all commands are counted , the counter will 
                // reach 0 if all the commands are valid , otherwise it 
                // will throw exception
                $lineCommands = array_map(function ($line) {
                    return '{%' . $line;
                }, explode('{%', $line));

                foreach ($validCommands as $command) {
                    foreach ($lineCommands as $lineCommand) {
                        if (preg_match($command, $lineCommand)) {
                            $commandsCount -= 1;
                        }
                    }
                }

                if ($commandsCount) {
                    throw new InvalidStatementException(
                        "Invalid statement : {$line} " .
                        "in template [{$this->template}]"
                    );
                }
            }

            $this->content[$i] = $line;
        }
    }
    
    /**
     * Handle defined variables in the template. 
     * 
     * @return bool
     */
    private function defineVariables()
    {
        foreach ($this->content as $i => $line) {
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
                        $match[2]
                    );

                    $this->content[$i] = str_replace(
                        $match[0],
                        '',
                        $this->content[$i]
                    );
                }
            }
        }
    }
    
    /**
     * Traverse through template lines and handle different cases. 
     * 
     * @return bool
     */
    private function processTemplate()
    {
        $updatedContent = [];
        $isBlankLine = false;
        $recheck = false;

        $this->removeComments();
        $this->cleanTemplate();
        $this->defineVariables();
        
        // in case the first line of the template was 'extend'
        // we need to handle it before any further processing 
        // on the template
        if (preg_match('~{% extend ([\"|\']+){1}([a-zA-Z0-9\.\-\_\/]+)(\1) %}~',
            $this->content[0], $match)
        ) {
            // remove the 'extend' directive
            $this->content[0] = '';

            // prepend the parent template content to the child 
            $this->content = array_merge(
                $this->getTemplateContent($match[2]),
                $this->content
            );            
        }

        $this->content = $this->blocksParser->parse(
            $this->content,
            $this->data
        );

        $this->content = $this->loopsParser->parse(
            $this->content,
            $this->data
        );

        $this->content = $this->conditionsParser->parse(
            $this->content,
            $this->data
        );

        foreach ($this->content as $i => $line) {
            $match = [];

            // remove unnecessary blank lines
            if (trim($line) == '') {
                if ($isBlankLine) {
                    continue;
                }

                $isBlankLine = true;
                $updatedContent[] = $line;
                continue;
            } else {
                $isBlankLine = false;
            }
            
            // append normal lines 
            if (!$this->phraseExists(['{%', '{{'], $line)) {
                $updatedContent[] = $line;
                continue;
            }

            // in case only '{{' exists , consider this a normal line
            if ($this->phraseExists(['{{'], $line) &&
                !$this->phraseExists(['}}'], $line)
            ) {
                $updatedContent[] = $line;
                continue;
            }
            
            // handle extend template case
            if (preg_match(
                '~{% extend ([\"|\']+){1}([a-zA-Z0-9\.\-\_\/]+)(\1) %}~',
                $line, $match))
            {
                $updatedContent = array_merge(
                    $updatedContent,
                    $this->getTemplateContent($match[2])
                );
                
                $recheck = true;
            }

            // handle include template case
            if (preg_match(
                '~{% include ([\"|\']+)([a-zA-Z0-9\.\-\_\/]+)(\1) %}~',
                $line, $match)
            ) {
                $updatedContent = array_merge(
                    $updatedContent,
                    $this->getTemplateContent($match[2])
                );

                $recheck = true;
            }

            // handle show block case
            if (preg_match(
                '~{% show_block ([\"|\']+)([a-zA-Z0-9\.\-\_]+)(\1) %}~',
                $line, $match)
            ) {
                if (!isset($this->blocksParser->blocks[$match[2]])) {
                    throw new TemplateParsingException(
                        "Undefined block [{$match[2]}] " .
                        "in template [{$this->template}]"
                    );
                }
                
                $blockBody = $this->blocksParser->blocks[$match[2]]['body'];

                // we replace the 'show_block' part with the block's first line
                // so if there're other text we don't delete it , we then
                // merge it back to the block's body as first line then
                // to the updated content
                if (count($blockBody) < 2) {
                    $line = str_replace($match[0], $blockBody[0], $line);
                    $blockBody[0] = $line;
                } else {
                    $lineParts = explode($match[0], $line);
                    $line = str_replace($match[0], '', $line);

                    $blockBody[0] = $lineParts[0] . $blockBody[0];
                    $blockBody[count($blockBody) - 1] =  
                        $blockBody[count($blockBody) - 1] . $lineParts[1]
                        ;
                }

                // save the update to the content 
                $this->content[$i] = $line;

                $updatedContent = array_merge($updatedContent, $blockBody);
                
                $recheck = true;
            }
            
            // process variables and expressions
            if (preg_match('~{{[^{}]+}}~', $line, $match)) {
                $line = ExpressionEvaluator::executeLine(
                    $line, 
                    $this->data
                );

                $this->content[$i] = $line;

                $updatedContent[] = $line;
            }
            
            // process variables and expressions
            if (preg_match('~{%\s*([a-zA-Z0-9\_]+)\((.*?)\)\s*%}~',
                $line, $match)
            ) {
                if (!isset($this->customDirectives[$match[1]])) {
                    throw new UndefinedDirectiveException(
                        "Undefined directive [{$match[1]}] " .
                        "in template [{$this->template}]"
                    );
                }

                if (isset($match[2]) && !empty($match[2])) {
                    $arguments = explode(',', $match[2]);

                    $arguments = array_map(function ($argument) {
                        return ExpressionEvaluator::execute($argument);
                    }, $arguments);
                }

                $result = call_user_func(
                    $this->customDirectives[$match[1]],
                    ...$arguments
                );

                $line = str_replace(
                    $match[0],
                    ' ' . $result . ' ',
                    $line
                );

                $this->content[$i] = $line;

                $updatedContent[] = $line;
            }
            
            // check if any further check is required
            if ($this->phraseExists([
                '{% if', '{% else_if', '{% else', '{% end_if',
                '{% for', '{% break', '{% continue', '{% end_for',
            ], $line)) {
                $updatedContent[] = $line;
                $recheck = true;
            }
        }

        $this->content = $updatedContent;

        return $recheck;
    }
}