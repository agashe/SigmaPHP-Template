<?php

namespace SigmaPHP\Template\Parsers;

use SigmaPHP\Template\Exceptions\TemplateParsingException;
use SigmaPHP\Template\Interfaces\ParserInterface;
use SigmaPHP\Template\ExpressionEvaluator;

/**
 * Conditions Parser Class 
 */
class ConditionsParser implements ParserInterface
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
     * @var array $conditions
     */
    public $conditions;

    /**
     * @var array $inlineConditions
     */
    public $inlineConditions;
    
    /**
     * Conditions Parser Constructor.
     */
    public function __construct() {
        $this->content = [];
        $this->data = [];
        $this->conditions = [];
        $this->inlineConditions = [];
    }

    /**
     * Handle nested inline if statements.
     * 
     * @return array
     */
    private function handleNestedInlineIfConditions()
    {
        $ifStartTag = [];
        $elseIfTag = [];
        $elseTag = [];
        $ifEndTag = [];
        $currentStartTags = [];
        $counter = 0;

        foreach ($this->content as $lineNumber => $contentLine) {
            // check only inline if statement
            if ((strpos($contentLine, '{% if') === false) ||
                (strpos($contentLine, '{% end_if') === false)
            ) {
                continue;
            }

            // we convert the inline if statement into a virtual condition block
            // we do all the normal process then we implode it again into one 
            // line
            $inlineConditionBlock = explode('{%', $contentLine);
            
            // remove first element , since it's always empty
            // but we keep the text so we can append again later
            // hen the process is done
            $textBeforeCondition = $inlineConditionBlock[0];
            unset($inlineConditionBlock[0]);

            // put back the opening tag '{%'
            $inlineConditionBlock = array_map(function ($line) {
                return '{%' . $line;
            }, $inlineConditionBlock);

            foreach ($inlineConditionBlock as $i => $line) {
                if (preg_match('~{% if \((.*?)\) %}~',
                    $line, $matchStartTag))
                {
                    $counter += 1;

                    $ifStartTag[$counter] = [
                        'tag' => $matchStartTag[0],
                        'expression' => $matchStartTag[1],
                        'id' => $counter,
                        'line' => $lineNumber
                    ];

                    $currentStartTags[] = $counter;
                }
                
                if (preg_match('~{% else_if \((.*?)\) %}~',
                    $line, $matchElseIfTag))
                {
                    // check if no 'else' , or throw exception , since we can't 
                    // have 'else_if' after 'else' 
                    if (isset($elseTag[end($currentStartTags)])) {
                        throw new TemplateParsingException(
                            "'else_if' used after 'else' statement " .
                            "in template [{$this->template}]"
                        );
                    }

                    $elseIfTag[end($currentStartTags)][] = [
                        'tag' => $matchElseIfTag[0],
                        'expression' => $matchElseIfTag[1],
                        'id' => end($currentStartTags),
                        'line' => $lineNumber
                    ];
                }
                
                if (preg_match('~{% else %}~', $line, $matchElseTag))
                {
                    $else = "{% else " . end($currentStartTags) . " %}";
                        
                    $inlineConditionBlock[$i] = str_replace(
                        '{% else %}',
                        $else,
                        $line
                    );
                    
                    $line = str_replace(
                        '{% else %}',
                        $else,
                        $line
                    );

                    $elseTag[end($currentStartTags)] = [
                        'tag' => $else,
                        'id' => end($currentStartTags),
                        'line' => $lineNumber
                    ];
                }

                if (preg_match_all('~{% end_if ~', $line, $matchEndTag)) {
                    $endingTag = $matchEndTag[0];

                    if (strpos($line, '{% end_if %}') !== false) {
                        $endingTag = "{% end_if " . 
                            end($currentStartTags) . " %}";
                        
                        $inlineConditionBlock[$i] = str_replace(
                            '{% end_if %}',
                            $endingTag,
                            $line
                        );
                    }

                    $ifEndTag[end($currentStartTags)] = [
                        'tag' => $endingTag,
                        'id' => end($currentStartTags),
                        'line' => $lineNumber
                    ];

                    // check first the 'else_if' , in case the condition doesn't  
                    // have one , the same for the else
                    $elseIfBlock = [];

                    if (isset($elseIfTag[end($currentStartTags)])) {
                        $elseIfBlock = $elseIfTag[end($currentStartTags)];
                    }

                    $elseBlock = [];

                    if (isset($elseTag[end($currentStartTags)])) {
                        $elseBlock = $elseTag[end($currentStartTags)];
                    }

                    // 'else' and 'else_if' outside condition block 
                    if (!isset($ifStartTag[end($currentStartTags)]) &&
                        isset($elseIfTag[end($currentStartTags)])
                    ) {
                        throw new TemplateParsingException(
                            "'else_if' used without an if statement " .
                            "in template [{$this->template}]"
                        );
                    }
                    
                    if (!isset($ifStartTag[end($currentStartTags)]) &&
                        isset($elseTag[end($currentStartTags)])
                    ) {
                        throw new TemplateParsingException(
                            "'else' used without an if statement " .
                            "in template [{$this->template}]"
                        );
                    }

                    $this->inlineConditions[end($currentStartTags)] = [
                        'start' => $ifStartTag[end($currentStartTags)] ?? 0,
                        'else_if' => $elseIfBlock,
                        'else' => $elseBlock,
                        'end' => $ifEndTag[end($currentStartTags)],
                    ];
                    
                    if ((count($currentStartTags) - 1) >= 0) {
                        unset($currentStartTags[count($currentStartTags) - 1]);
                        $currentStartTags = array_values($currentStartTags);
                    }

                    // save the line again
                    $this->content[$lineNumber] = $textBeforeCondition . 
                        implode(' ', $inlineConditionBlock);
                }
            }
        }

        // if conditions open/close tags aren't matched , throw exception
        if (count($ifStartTag) != count($ifEndTag)) {
            if (isset($ifStartTag[0])) {
                throw new TemplateParsingException(
                    "Missing 'end_if' tag for an if statement" .
                    "in template [{$this->template}]"
                );
            } else {
                throw new TemplateParsingException(
                    "'end_if' used without an if statement " .
                    "in template [{$this->template}]"
                );
            }
        }

        // 'else' and 'else_if' outside condition block 
        if (count($ifStartTag) == 0 &&
            count($ifEndTag) == 0 &&
            count($elseIfTag) != 0
        ) {
            throw new TemplateParsingException(
                "'else_if' used without an if statement " .
                "in template [{$this->template}]"
            );
        }
        
        if (count($ifStartTag) == 0 &&
            count($ifEndTag) == 0 &&
            count($elseTag) != 0
        ) {
            throw new TemplateParsingException(
                "'else' used without an if statement " .
                "in template [{$this->template}]"
            );
        }
    }

    /**
     * Execute inline if statements.
     * 
     * @return void
     */
    private function executeInlineConditions()
    {
        if (empty($this->inlineConditions)) {
            return;
        }

        foreach (array_reverse($this->inlineConditions) as $condition) {
            // first we check if the condition was already processed
            if (
                strpos(
                    $this->content[$condition['start']['line']],
                    '{% if'   
                ) === false
            ) {
                continue;
            }

            // we use these boundaries to replace the whole if statement with 
            // the correct answer or delete it
            $ifStartBoundary = preg_quote($condition['start']['tag']);
            $ifEndBoundary = preg_quote($condition['end']['tag']);

            $result = (bool) ExpressionEvaluator::execute(
                $condition['start']['expression'], 
                $this->data
            );

            if ($result) {
                // we get all the text between the condition start and stop when
                // hit a stop , it could be an 'else_if' , 'else' or 'end_if' 
                $startBoundary = preg_quote($condition['start']['tag']);

                if (!empty($condition['else_if'])) {
                    $endBoundary = preg_quote($condition['else_if'][0]['tag']);
                }
                else if (!empty($condition['else'])) {
                    $endBoundary = preg_quote($condition['else']['tag']);
                }
                else {
                    $endBoundary = preg_quote($condition['end']['tag']);
                }
                
                $conditionContent = '';
                
                preg_match(
                    "~$startBoundary\s*(.*?)\s*$endBoundary~", 
                    $this->content[$condition['start']['line']],
                    $conditionContent
                );

                if (isset($conditionContent[1])) {
                    $this->content[$condition['start']['line']] =
                        preg_replace(
                            "~$ifStartBoundary\s*(.*?)\s*$ifEndBoundary~",
                            $conditionContent[1],
                            $this->content[$condition['start']['line']]
                        );
                } else {
                    $this->content[$condition['start']['line']] =
                        preg_replace(
                            "~$ifStartBoundary\s*(.*?)\s*$ifEndBoundary~",
                            '',
                            $this->content[$condition['start']['line']]
                        );
                }
            } else {
                $elseIfResult = false;

                if (!empty($condition['else_if'])) {
                    foreach ($condition['else_if'] as $key => $elseIf) {
                        $elseIfResult = (bool) ExpressionEvaluator::execute(
                            $elseIf['expression'], 
                            $this->data
                        );
            
                        if ($elseIfResult) {
                            // we get the text between the current 'else_if'
                            // and the following 'else_if' , the next 'else'
                            // or the 'end_if'
                            $startBoundary = preg_quote(
                                $elseIf['tag']
                            );

                            if (isset($condition['else_if'][$key + 1])) {
                                $endBoundary = preg_quote(
                                    $condition['else_if'][$key + 1]['tag']
                                );
                            }
                            else if (!empty($condition['else']['tag'])) {
                                $endBoundary = preg_quote(
                                    $condition['else']['tag']
                                );
                            }
                            else {
                                $endBoundary = preg_quote(
                                    $condition['end']['tag']
                                );
                            }
            
                            $conditionContent = '';
                
                            preg_match(
                                "~$startBoundary\s*(.*?)\s*$endBoundary~", 
                                $this->content[$condition['start']['line']],
                                $conditionContent
                            );

                            if (isset($conditionContent[1])) {
                                $this->content[$condition['start']['line']] =
                                    preg_replace(
                                        "~$ifStartBoundary\s*(.*?)\s*$ifEndBoundary~",
                                        $conditionContent[1],
                                        $this->content[
                                            $condition['start']['line']
                                        ]
                                    );
                            } else {
                                $this->content[$condition['start']['line']] =
                                    preg_replace(
                                        "~$ifStartBoundary\s*(.*?)\s*$ifEndBoundary~",
                                        '',
                                        $this->content[
                                            $condition['start']['line']
                                        ]
                                    );
                            }
                                
                            break;
                        }
                    }
                }
                
                if (!$elseIfResult) {
                    if (!empty($condition['else'])) {
                        // get all text between the 'else' and the 'end_if'
                        $startBoundary = preg_quote(
                            $condition['else']['tag']
                        );

                        $endBoundary = preg_quote(
                            $condition['end']['tag']
                        );
        
                        $conditionContent = '';
                        
                        preg_match(
                            "~$startBoundary\s*(.*?)\s*$endBoundary~", 
                            $this->content[$condition['start']['line']],
                            $conditionContent
                        );

                        if (isset($conditionContent[1])) {
                            $this->content[$condition['start']['line']] =
                                preg_replace(
                                    "~$ifStartBoundary\s*(.*?)\s*$ifEndBoundary~",
                                    $conditionContent[1],
                                    $this->content[$condition['start']['line']]
                                );
                        } else {
                            $this->content[$condition['start']['line']] =
                                preg_replace(
                                    "~$ifStartBoundary\s*(.*?)\s*$ifEndBoundary~",
                                    '',
                                    $this->content[$condition['start']['line']]
                                );
                        }
                    }
                    else {
                        // if no 'else_if' or 'else' , delete the whole block
                        $this->content[$condition['start']['line']] =
                            preg_replace(
                                "~$ifStartBoundary\s*(.*?)\s*$ifEndBoundary~",
                                '',
                                $this->content[$condition['start']['line']]
                            );
                    }
                }
            }
        }

        // remove all processed inline conditions
        $this->inlineConditions = [];
    }

    /**
     * Handle nested if condition blocks.
     * 
     * @return array
     */
    private function handleNestedIfConditions()
    {
        $ifStartTag = [];
        $elseIfTag = [];
        $elseTag = [];
        $ifEndTag = [];
        $currentStartTags = [];
        $counter = 0;

        foreach ($this->content as $i => $line) {
            // skip inline if statement
            if ((strpos($line, '{% if') !== false) && 
                (strpos($line, '{% end_if') !== false)
            ) {
                continue;
            }

            if (preg_match('~{% if \((.*?)\) %}~',
                $line, $matchStartTag))
            {
                $counter += 1; // why increment before not after ??????

                $ifStartTag[$counter] = [
                    'tag' => $matchStartTag[0],
                    'expression' => $matchStartTag[1],
                    'id' => $counter,
                    'line' => $i
                ];

                $currentStartTags[] = $counter;
            }
            
            if (preg_match('~{% else_if \((.*?)\) %}~',
                $line, $matchElseIfTag))
            {
                // check if no 'else' , or throw exception , since we can't have
                // else_if after 'else' 
                if (isset($elseTag[end($currentStartTags)])) {
                    throw new TemplateParsingException(
                        "'else_if' used after 'else' statement " .
                        "in template [{$this->template}]"
                    );
                }

                $elseIfTag[end($currentStartTags)][] = [
                    'tag' => $matchElseIfTag[0],
                    'expression' => $matchElseIfTag[1],
                    'id' => end($currentStartTags),
                    'line' => $i
                ];
            }
            
            if (preg_match('~{% else %}~', $line, $matchElseTag))
            {
                $else = "{% else " . end($currentStartTags) . " %}";
                    
                $this->content[$i] = str_replace(
                    '{% else %}',
                    $else,
                    $line
                );
                
                $line = str_replace(
                    '{% else %}',
                    $else,
                    $line
                );

                $elseTag[end($currentStartTags)] = [
                    'tag' => $else,
                    'id' => end($currentStartTags),
                    'line' => $i
                ];
            }

            if (preg_match('~{% end_if ~', $line, $matchEndTag)) {
                $endingTag = $matchEndTag[0];

                if (strpos($line, '{% end_if %}') !== false) {
                    $endingTag = "{% end_if " . end($currentStartTags) . " %}";
                    
                    $this->content[$i] = str_replace(
                        '{% end_if %}',
                        $endingTag,
                        $line
                    );
                }

                $ifEndTag[end($currentStartTags)] = [
                    'tag' => $endingTag,
                    'id' => end($currentStartTags),
                    'line' => $i
                ];

                // check first 'else_if' , in case the condition doesn't have 
                // one , the same for the else
                $elseIfBlock = [];

                if (isset($elseIfTag[end($currentStartTags)])) {
                    $elseIfBlock = $elseIfTag[end($currentStartTags)];
                }

                $elseBlock = [];

                if (isset($elseTag[end($currentStartTags)])) {
                    $elseBlock = $elseTag[end($currentStartTags)];
                }

                // 'else' and 'else_if' outside condition block 
                if (!isset($ifStartTag[end($currentStartTags)]) &&
                    isset($elseIfTag[end($currentStartTags)])
                ) {
                    throw new TemplateParsingException(
                        "'else_if' used without an if statement " .
                        "in template [{$this->template}]"
                    );
                }
                
                if (!isset($ifStartTag[end($currentStartTags)]) &&
                    isset($elseTag[end($currentStartTags)])
                ) {
                    throw new TemplateParsingException(
                        "'else' used without an if statement " .
                        "in template [{$this->template}]"
                    );
                }

                $this->conditions[] = [
                    'start' => $ifStartTag[end($currentStartTags)] ?? 0,
                    'else_if' => $elseIfBlock,
                    'else' => $elseBlock,
                    'end' => $ifEndTag[end($currentStartTags)],
                ];
                
                if ((count($currentStartTags) - 1) >= 0) {
                    unset($currentStartTags[count($currentStartTags) - 1]);
                    $currentStartTags = array_values($currentStartTags);
                }
            }
        }

        // if conditions open/close tags aren't matched , throw exception
        if (count($ifStartTag) != count($ifEndTag)) {
            if (isset($ifStartTag[0])) {
                throw new TemplateParsingException(
                    "Missing 'end_if' tag for an if statement" .
                    "in template [{$this->template}]"
                );
            } else {
                throw new TemplateParsingException(
                    "'end_if' used without an if statement " .
                    "in template [{$this->template}]"
                );
            }
        }

        // 'else' and 'else_if' outside condition block 
        if (count($ifStartTag) == 0 &&
            count($ifEndTag) == 0 &&
            count($elseIfTag) != 0
        ) {
            throw new TemplateParsingException(
                "'else_if' used without an if statement " .
                "in template [{$this->template}]"
            );
        }
        
        if (count($ifStartTag) == 0 &&
            count($ifEndTag) == 0 &&
            count($elseTag) != 0
        ) {
            throw new TemplateParsingException(
                "'else' used without an if statement " .
                "in template [{$this->template}]"
            );
        }
    }

    /**
     * Execute if statements.
     * 
     * @return void
     */
    private function executeConditionBlocks()
    {
        if (empty($this->conditions)) {
            return;
        }

        foreach ($this->conditions as $condition) {
            // first we check if the condition block was already processed
            if (
                strpos(
                    $this->content[$condition['start']['line']],
                    '{% if'   
                ) === false
            ) {
                continue;
            }

            $deleteLines = false;

            $result = (bool) ExpressionEvaluator::execute(
                $condition['start']['expression'], 
                $this->data
            );

            if ($result) {
                // in case of correct 'if' , delete start tag and everything
                // after the end of the 'if' block , which could be 
                // 'else' , 'else_if' , or 'end_if'
                $this->content[$condition['start']['line']] = str_replace(
                    $condition['start']['tag'],
                    '', 
                    $this->content[$condition['start']['line']]
                );

                // in case no 'else_if' and 'else' , just replace the 'end_if'
                // otherwise remove the text before also
                if (empty($condition['else_if']) && empty($condition['else']))
                {
                    $this->content[$condition['end']['line']] = str_replace(
                        $condition['end']['tag'],
                        '', 
                        $this->content[$condition['end']['line']]
                    );
                } else {
                    $endLineParts = explode(
                        $condition['end']['tag'],
                        $this->content[$condition['end']['line']]    
                    );

                    $this->content[$condition['end']['line']] = 
                        $endLineParts[1];
                }

                for (
                    $i = $condition['start']['line'] + 1;
                    $i < $condition['end']['line'];
                    $i++
                ) {
                    if ($deleteLines) {
                        $this->content[$i] = '';
                    } else {
                        // in case the 'if' block will end by 'else_if' or 
                        // 'else' we remove the tag and all after
                        if (preg_match('~{% else_if \((.*?)\) %}~',
                            $this->content[$i], $matchElseIfTag)
                        ) {
                            $elseIfLineParts = explode(
                                $matchElseIfTag[0],
                                $this->content[$i]
                            );

                            $this->content[$i] = $elseIfLineParts[0];

                            $deleteLines = true;
                        }
                        
                        if (preg_match('~{% else ([0-9]+) %}~', 
                            $this->content[$i], $matchElseTag)
                        ) {
                            $elseLineParts = explode(
                                $matchElseTag[0],
                                $this->content[$i]
                            );
    
                            $this->content[$i] = $elseLineParts[0];
                            $deleteLines = true;
                        }
                    }
                }
            } else {
                $elseIfResult = false;

                if (!empty($condition['else_if'])) {
                    // in case of 'else if' , if the condition hit , then we
                    // delete all of condition block's lines then , keep the 
                    // 'else if' body
                    foreach ($condition['else_if'] as $elseIf) {
                        $elseIfResult = (bool) ExpressionEvaluator::execute(
                            $elseIf['expression'], 
                            $this->data
                        );
            
                        if ($elseIfResult) {
                            $deleteLines = true;
                            break;
                        }
                    }

                    if ($elseIfResult) {
                        $startLineParts = explode(
                            $condition['start']['tag'],
                            $this->content[$condition['start']['line']]
                        );

                        $this->content[$condition['start']['line']] = 
                            $startLineParts[0];
                        
                        $endLineParts = explode(
                            $condition['end']['tag'],
                            $this->content[$condition['end']['line']]
                        );

                        $this->content[$condition['end']['line']] = 
                            $endLineParts[1];

                        for (
                            $i = $condition['start']['line'] + 1;
                            $i < $condition['end']['line'];
                            $i++
                        ) {
                            if ($i == $elseIf['line']) {
                                $elseIfLineParts = explode(
                                    $elseIf['tag'],
                                    $this->content[$i]
                                );

                                $this->content[$i] = $elseIfLineParts[1];

                                $deleteLines = false;
                            } else {
                                // in case the 'else_if' block will end by 
                                // 'else_if' or 'else' we remove the tag and 
                                // all after
                                if (!$deleteLines &&
                                    preg_match('~{% else_if \((.*?)\) %}~',
                                        $this->content[$i], $matchElseIfTag)
                                ) {
                                    $elseIfLineParts = explode(
                                        $matchElseIfTag[0],
                                        $this->content[$i]
                                    );

                                    $this->content[$i] = $elseIfLineParts[0];

                                    $deleteLines = true;
                                    continue 1;
                                }
                                else if (!$deleteLines &&
                                    preg_match('~{% else ([0-9]+) %}~', 
                                        $this->content[$i], $matchElseTag)
                                ) {
                                    $elseLineParts = explode(
                                        $matchElseTag[0],
                                        $this->content[$i]
                                    );

                                    $this->content[$i] = $elseLineParts[0];
                                    $deleteLines = true;
                                    continue 1;
                                }
                            }

                            if ($deleteLines) {
                                $this->content[$i] = '';
                            }
                        }
                    }
                }
                
                if (!$elseIfResult) {
                    if (!empty($condition['else'])) {
                        // in case of 'else' , delete everything before the
                        // 'else' and the 'end_if' 
                        $startLineParts = explode(
                            $condition['start']['tag'],
                            $this->content[$condition['start']['line']]
                        );

                        $this->content[$condition['start']['line']] = 
                            $startLineParts[0];
                        
                        $this->content[$condition['end']['line']] = 
                            str_replace(
                                $condition['end']['tag'],
                                '', 
                                $this->content[$condition['end']['line']]
                            );

                        $elseLineParts = explode(
                            $condition['else']['tag'],
                            $this->content[$condition['else']['line']]
                        );

                        $this->content[$condition['else']['line']] = 
                            $elseLineParts[1];

                        for (
                            $i = $condition['start']['line'] + 1;
                            $i < $condition['else']['line'];
                            $i++
                        ) {
                            if ($i == $condition['else']) {
                                continue;
                            }

                            $this->content[$i] = '';
                        }    
                    }
                    else {
                        // if no 'else' delete the whole condition block
                        // but we keep all before and after , in case
                        // there's some text on same line of the tags
                        $startLineParts = explode(
                            $condition['start']['tag'],
                            $this->content[$condition['start']['line']]
                        );

                        $this->content[$condition['start']['line']] = 
                            $startLineParts[0];
                        
                        $endLineParts = explode(
                            $condition['end']['tag'],
                            $this->content[$condition['end']['line']]
                        );

                        $this->content[$condition['end']['line']] = 
                            $endLineParts[1];
                        
                        for (
                            $i = $condition['start']['line'] + 1;
                            $i < $condition['end']['line'];
                            $i++
                        ) {
                            $this->content[$i] = '';
                        }
                    }
                }
            }
        }

        // remove all processed conditions
        $this->conditions = [];
    }

    /**
     * Parse conditions in a template.
     * 
     * @param array $content
     * @param array $data
     * @return array
     */
    final public function parse($content, $data = [])
    {
        $this->content = $content;
        $this->data = $data;

        $this->handleNestedInlineIfConditions();
        $this->executeInlineConditions();
        
        $this->handleNestedIfConditions();
        $this->executeConditionBlocks();

        return $this->content;
    }
}