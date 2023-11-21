<?php

namespace SigmaPHP\Template\Parsers;

use SigmaPHP\Template\Exceptions\InvalidExpressionException;
use SigmaPHP\Template\Exceptions\TemplateParsingException;
use SigmaPHP\Template\Interfaces\ParserInterface;
use SigmaPHP\Template\ExpressionEvaluator;

/**
 * Loops Parser Class 
 */
class LoopsParser implements ParserInterface
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
     * @var array $loops
     */
    public $loops;

    /**
     * @var array $inlineLoops
     */
    public $inlineLoops;

    /**
     * @var \ConditionsParser $conditionsParser
     */
    private $conditionsParser;

    /**
     * Loops Parser Constructor.
     */
    public function __construct() {
        $this->template = '';
        $this->content = [];
        $this->data = [];
        $this->loops = [];
        $this->inlineLoops = [];

        $this->conditionsParser = new ConditionsParser();
    }

    /**
     * Extract loop body and remove 'break' and 'continue' statements.
     * 
     * @param array $loop
     * @return array
     */
    private function extractLoopBody($loop)
    {
        $loopBody = [];
        $hasInnerLoop = false;

        // first we extract old iterations values if exists
        for (
            $i = ($loop['start']['line'] + 1);
            $i < $loop['end']['line'];
            $i++
        ) {           
            if (preg_match('~<% (.*?)=(.*?) %>~', 
                $this->content[$i], $match)
            ) {
                $this->data[$match[1]] = ExpressionEvaluator::execute(
                    $match[2],
                    $this->data
                );
            }
        }

        // save text after the loop's start tag , since this is 
        // part of the loop body , need to processed. Same for
        // end tag
        $lineParts = explode(
            $loop['start']['tag'],
            $this->content[$loop['start']['line']]
        );

        if (preg_match('~{{(.*?)}}~', $lineParts[1])) {
            $loopBody[] = ExpressionEvaluator::executeLine(
                $lineParts[1], 
                $this->data
            );
        } else {
            $loopBody[] = $lineParts[1];
        }
        
        for (
            $i = ($loop['start']['line'] + 1);
            $i < $loop['end']['line'];
            $i++
        ) {
            // process variables and expressions
            if (!$hasInnerLoop && preg_match('~{{(.*?)}}~',
                $this->content[$i])
            ) {
                $loopBody[] = ExpressionEvaluator::executeLine(
                    $this->content[$i], 
                    $this->data
                );
            } else {
                if (preg_match('~{% break \((.*?)\) \<([0-9]+)\> %}~',
                    $this->content[$i], $matchBreak)
                ) {
                    $loopBody[] = str_replace(
                        $matchBreak[0],
                        '',
                        $this->content[$i]
                    );

                    continue;
                }
                
                if (preg_match('~{% continue \((.*?)\) \<([0-9]+)\> %}~',
                    $this->content[$i], $matchContinue)
                ) {
                    $loopBody[] = str_replace(
                        $matchContinue[0],
                        '',
                        $this->content[$i]
                    );

                    continue;
                }

                $loopBody[] = $this->content[$i];

                // please note : in case the loop has nested loops , we save 
                // temporarily the current iterator value in '<%%>' syntax , 
                // then we remove that once the the value is executed in the 
                // nested loops
                if (preg_match('~{% for \$(.*?) in (.*?) %}~',
                    $this->content[$i], $matchStartLoop)
                ) {
                    $hasInnerLoop = true;

                    $loopBody[] = '<% ' . $loop['start']['value'] . '=' . 
                        $this->data[$loop['start']['value']] . ' %>';
                }

                if ($hasInnerLoop &&
                    strpos($this->content[$i], '{% end_for %}') !== false
                ) {
                    $hasInnerLoop = false;
                }
            }
        }

        $lineParts = explode(
            $loop['end']['tag'],
            $this->content[$loop['end']['line']]
        );
        
        if (preg_match('~{{(.*?)}}~', $lineParts[0])) {
            $loopBody[] = ExpressionEvaluator::executeLine(
                $lineParts[0], 
                $this->data
            );
        } else {
            $loopBody[] = $lineParts[0];
        }

        return $loopBody;
    }

    /**
     * Check if break expression was resolved in a loop.
     * 
     * @param array $loop
     * @return bool
     */
    private function breakIsResolved($loop)
    {
        if (empty($loop['break'])) {
            return false;
        }
        
        foreach ($loop['break'] as $breakStatement) {
            $result = ExpressionEvaluator::execute(
                $breakStatement['expression'], 
                $this->data
            );

            if ($result) {
                return true; 
            }
        }

        return false;
    }

    /**
     * Check if continue expression was resolved in a loop.
     * 
     * @param array $loop
     * @return bool
     */
    private function continueIsResolved($loop)
    {
        if (empty($loop['continue'])) {
            return false;
        }

        foreach ($loop['continue'] as $continueStatement) {
            $result = ExpressionEvaluator::execute(
                $continueStatement['expression'], 
                $this->data
            );

            if ($result) {
                return true; 
            }
        }

        return false;
    }

    /**
     * Get loop id vy matching its start tag.
     * 
     * @param string $startTag
     * @return int|bool
     */
    private function getLoopByStartTag($startTag)
    {
        foreach ($this->loops as $id => $loop) {
            if ($loop['start']['tag'] == $startTag) {
                return $id; 
            }
        }

        return false;
    }

    /**
     * Get loop iteration group.
     * 
     * @param string $expression
     * @return array
     */
    private function getLoopIterationGroup($expression)
    {
        extract($this->data);

        $group = [];

        $expression = ExpressionEvaluator::execute(
            $expression,
            $this->data
        );
        
        if (is_numeric($expression)) {
            for ($j = 1;$j <= (int) $expression;$j++) {
                $group[] = (int) $j;
            }
        }
        else if (is_string($expression)) {
            $group = str_split($expression);
        }
        else if (is_array($expression)) {
            $group = $expression;
        } else {
            // if the expression isn't iterable , throw exception
            throw new InvalidExpressionException(
                "Invalid loop expression : {$expression} " 
                . "in template [{$this->template}]"
            );
        }

        return $group;
    }

    /**
     * Handle nested inline loops.
     * 
     * @return array
     */
    private function handleNestedInlineLoops()
    {
        $loopStartTag = [];
        $breakLoopTag = [];
        $continueLoopTag = [];
        $loopEndTag = [];
        $currentStartTags = [];
        $counter = 0;
        $skipLoop = false;

        foreach ($this->content as $lineNumber => $contentLine) {
            // check only inline loops
            if ((strpos($contentLine, '{% for') === false) ||
                (strpos($contentLine, '{% end_for') === false)
            ) {
                continue;
            }

            // we convert the inline loop into a virtual loop block
            // we do all the normal process then we implode it again into one 
            // line
            $inlineLoopBlock = explode('{%', $contentLine);
            
            // remove first element , since it's always empty
            // but we keep the text so we can append again later
            // hen the process is done
            $textBeforeLoop = $inlineLoopBlock[0];
            unset($inlineLoopBlock[0]);

            // put back the opening tag '{%'
            $inlineLoopBlock = array_map(function ($line) {
                return '{%' . $line;
            }, $inlineLoopBlock);

            foreach ($inlineLoopBlock as $i => $line) {
                if (preg_match(
                        '~{% for \$([a-zA-Z0-9_]+) in (.*?) \(([0-9]+)\) %}~',
                        $line, 
                        $matchStartTag
                    )
                ) {
                    $skipLoop = true;
                    continue;
                }

                if (preg_match('~{% for \$([a-zA-Z0-9_]+) in (.*?) %}~',
                    $line, $matchStartTag)
                ) {
                    // add loop id to the start tag
                    $updatedLoopStartTag = '{% for $' . $matchStartTag[1] .
                        ' in ' . $matchStartTag[2] . " ($counter) %}";

                    if (empty($currentStartTags)) {
                        $inlineLoopBlock[$i] = str_replace(
                            $matchStartTag[0],
                            $updatedLoopStartTag,
                            $inlineLoopBlock[$i]
                        );
                    }

                    $loopStartTag[$counter] = [
                        'tag' => $updatedLoopStartTag,
                        'value' => $matchStartTag[1],
                        'expression' => $matchStartTag[2],
                        'id' => $counter,
                        'line' => $lineNumber,
                        'is_nested' => (!empty($currentStartTags))
                    ];

                    $currentStartTags[] = $counter;

                    // initialize the value variable (the iterator)
                    if (!isset($this->data[$matchStartTag[1]])) {
                        $this->data[$matchStartTag[1]] = '';
                    }
                    
                    $counter += 1;

                    $skipLoop = false;
                }
                
                if (!$skipLoop && preg_match('~{% break \((.*?)\) %}~',
                    $line, $matchBreakTag)
                ) {
                    // add loop id to the break tag
                    $updatedBreakTag = '{% break (' . $matchBreakTag[1] . ') <' 
                        . end($currentStartTags) . '> %}';

                    $inlineLoopBlock[$i] = str_replace(
                        $matchBreakTag[0],
                        $updatedBreakTag,
                        $inlineLoopBlock[$i]
                    );

                    $breakLoopTag[end($currentStartTags)][] = [
                        'tag' => $updatedBreakTag,
                        'expression' => $matchBreakTag[1],
                        'id' => end($currentStartTags),
                        'line' => $lineNumber
                    ];
                }
                
                if (!$skipLoop && preg_match('~{% continue \((.*?)\) %}~',
                    $line, $matchContinueTag)
                ) {
                    // add loop id to the continue tag
                    $updatedContinueTag = '{% continue (' . 
                        $matchContinueTag[1] . ') <' 
                        . end($currentStartTags) . '> %}';

                    $inlineLoopBlock = str_replace(
                        $matchContinueTag[0],
                        $updatedContinueTag,
                        $inlineLoopBlock
                    );

                    $continueLoopTag[end($currentStartTags)][] = [
                        'tag' => $updatedContinueTag,
                        'expression' => $matchContinueTag[1],
                        'id' => end($currentStartTags),
                        'line' => $lineNumber
                    ];
                }

                if (!$skipLoop && strpos($line, '{% end_for %}') !== false) {
                    $endingTag = "{% end_for " . end($currentStartTags) . " %}";
                    
                    $inlineLoopBlock[$i] = str_replace(
                        '{% end_for %}',
                        $endingTag,
                        $line
                    );

                    $line = str_replace(
                        '{% end_for %}',
                        $endingTag,
                        $line
                    );
                }
                
                if (!$skipLoop && preg_match('~{% end_for ([0-9]+) %}~', 
                    $line, $matchEndTag)
                ) {
                    $endingTag = $matchEndTag[0];

                    $loopEndTag[end($currentStartTags)] = [
                        'tag' => $endingTag,
                        'id' => end($currentStartTags),
                        'line' => $lineNumber
                    ];

                    // 'break' and 'continue' outside condition block 
                    if (!isset($loopStartTag[end($currentStartTags)]) &&
                        isset($breakLoopTag[end($currentStartTags)])
                    ) {
                        throw new TemplateParsingException(
                            "'break' statement was used outside loop " .
                            "in template [{$this->template}]"
                        );
                    }
                    
                    if (!isset($loopStartTag[end($currentStartTags)]) &&
                        isset($continueLoopTag[end($currentStartTags)])
                    ) {
                        throw new TemplateParsingException(
                            "'continue' statement was used outside loop " .
                            "in template [{$this->template}]"
                        );
                    }

                    if (
                        isset($loopStartTag[end($currentStartTags)]) &&
                        isset($loopEndTag[end($currentStartTags)])
                    ) {
                        $breakLoop = [];

                        if (isset($breakLoopTag[end($currentStartTags)])) {
                            $breakLoop = $breakLoopTag[end($currentStartTags)];
                        }

                        $continueLoop = [];

                        if (isset($continueLoopTag[end($currentStartTags)])) {
                            $continueLoop = $continueLoopTag[
                                end($currentStartTags)
                            ];
                        }

                        if (
                            !$loopStartTag[end($currentStartTags)]['is_nested']
                        ) {
                            $this->inlineLoops[] = [
                                'start' => $loopStartTag[
                                    end($currentStartTags)
                                ],
                                'break' => $breakLoop,
                                'continue' => $continueLoop,
                                'end' => $loopEndTag[end($currentStartTags)],
                            ];
                        } else {
                            // in case of nested loops , we return the normal
                            // 'end_for' , so no confusing happen
                            $inlineLoopBlock[$i] = str_replace(
                                $endingTag,
                                '{% end_for %}',
                                $line
                            );
                        }

                        // reset 'break' and 'continue'
                        $breakLoopTag = [];
                        $continueLoopTag = [];
                    }
                    
                    if ((count($currentStartTags) - 1) >= 0) {
                        unset($currentStartTags[count($currentStartTags) - 1]);
                        $currentStartTags = array_values($currentStartTags);
                    }
                }
            }

            // loops open/close tags aren't matched , throw exception
            if (count($loopStartTag) != count($loopEndTag)) {
                if (isset($loopStartTag[0])) {
                    throw new TemplateParsingException(
                        "Missing 'end_for' tag for an loop " .
                        "in template [{$this->template}]"
                    );
                } else {
                    throw new TemplateParsingException(
                        "'end_for' used without an loop " .
                        "in template [{$this->template}]"
                    );
                }
            }

            // 'break' and 'continue' outside loop block 
            if (count($loopStartTag) == 0 &&
                count($loopEndTag) == 0 &&
                count($breakLoopTag) != 0
            ) {
                throw new TemplateParsingException(
                    "'break' statement was used outside loop " .
                    "in template [{$this->template}]"
                );
            }
            
            if (count($loopStartTag) == 0 &&
                count($loopEndTag) == 0 &&
                count($continueLoopTag) != 0
            ) {
                throw new TemplateParsingException(
                    "'continue' statement was used outside loop " .
                    "in template [{$this->template}]"
                );
            }

            // return the content line , with the processed loops
            $this->content[$lineNumber] = $textBeforeLoop .
                implode(' ', $inlineLoopBlock);
        }
    }

    /**
     * Execute inline loops.
     * 
     * @return void
     */
    private function executeInlineLoops()
    {
        if (empty($this->inlineLoops)) {
            return;
        }

        foreach ($this->inlineLoops as $loop) {
            // first we check if the loop was already processed
            if (
                strpos(
                    $this->content[$loop['start']['line']],
                    '{% for'   
                ) === false
            ) {
                continue;
            }
            
            // we use these boundaries to replace the whole loop with the body
            $loopStartBoundary = preg_quote($loop['start']['tag']);
            $loopEndBoundary = preg_quote($loop['end']['tag']);

            preg_match(
                "~$loopStartBoundary\s*(.*?)\s*$loopEndBoundary~",
                $this->content[$loop['start']['line']],
                $match
            );
            
            $loopBody = $match[1];
            
            // remove the 'break' and 'continue'
            foreach ($loop['break'] as $breakStatement) {
                $loopBody = str_replace(
                    $breakStatement['tag'], 
                    '',
                    $loopBody
                );
            }

            foreach ($loop['continue'] as $continueStatement) {
                $loopBody = str_replace(
                    $continueStatement['tag'], 
                    '',
                    $loopBody
                );
            }
            
            // prepare the expression , we convert the loop expression
            // to a group of items , so we can start looping
            $group = $this->getLoopIterationGroup($loop['start']['expression']);
            
            $loopBlock = '';
            $loopBlockContent = '';
            
            foreach ($group as $item) {
                $loopBlock = $loopBody;
                
                $this->data[$loop['start']['value']] = $item;
                
                if ($this->breakIsResolved($loop)) {
                    break 1;
                }

                if ($this->continueIsResolved($loop)) {
                    continue 1;
                }

                // handle nested loops , we remove them temporarily to evaluate
                // the current body then we get them back
                $loopBodyWithoutNestedLoops = preg_replace(
                    "~{% for (.*?) %}\s*(.*?)\s*{% end_for %}~",
                    '',
                    $loopBlock
                );

                // handle parent loops values
                preg_match_all(
                    '~<% (.*?)=(.*?) %>~',
                    $loopBodyWithoutNestedLoops,
                    $matches,
                    PREG_SET_ORDER
                );

                $oldLoopValues = '';
                if (!empty($matches[0])) {
                    foreach ($matches as $match) {
                        $this->data[$match[1]] = ExpressionEvaluator::execute(
                            $match[2], 
                            $this->data
                        );

                        $oldLoopValues .= $match[0];
                    }
                }

                // process conditions in loops
                preg_match_all(
                    '~{% if .*? %}.*?{% end_if %}~',
                    $loopBodyWithoutNestedLoops,
                    $matches
                );

                if (!empty($matches[0])) {
                    foreach ($matches as $match) {
                        $conditionValue = $this->conditionsParser->parse(
                            [$match[0]],
                            $this->data
                        )[0];
                        
                        $loopBlock = str_replace(
                            $match[0],
                            $conditionValue,
                            $loopBlock
                        );
                    }
                }
                
                // process expressions
                $updatedLoopBody = '';

                if (preg_match('~{{(.*?)}}~',$loopBodyWithoutNestedLoops)) {
                    $updatedLoopBody = ExpressionEvaluator::executeLine(
                        $loopBlock, 
                        $this->data
                    );
                } else {
                    $updatedLoopBody = ' ' . $loopBlock . ' ';
                }

                // for nested loops we save the current value
                preg_match_all(
                    '~{% for .*? %}~',
                    $updatedLoopBody,
                    $matches
                );

                if (!empty($matches[0])) {
                    foreach ($matches as $match) {   
                        $updatedLoopBody = str_replace(
                            $match[0],
                            $match[0] . $oldLoopValues .' <% ' . 
                                $loop['start']['value'] . '=' . $item . ' %> ',
                            $updatedLoopBody
                        );
                    }
                }

                $loopBlockContent .= $updatedLoopBody;
            }

            $this->content[$loop['start']['line']] =
                preg_replace(
                    "~$loopStartBoundary\s*(.*?)\s*$loopEndBoundary~",
                    $loopBlockContent,
                    $this->content[$loop['start']['line']]
                );
        }
        
        // delete all handled inline loops
        $this->inlineLoops = [];
    }

    /**
     * Handle nested loop blocks.
     * 
     * @return array
     */
    private function handleNestedLoops()
    {
        $loopStartTag = [];
        $breakLoopTag = [];
        $continueLoopTag = [];
        $loopEndTag = [];
        $currentStartTags = [];
        $counter = 0;
        $skipLoop = false;

        foreach ($this->content as $i => $line) {
            // skip inline loops
            if ((strpos($line, '{% for') !== false) && 
                (strpos($line, '{% end_for') !== false)
            ) {
                continue;
            }

            // skip already processed loops
            if (preg_match(
                    '~{% for \$([a-zA-Z0-9_]+) in (.*?) \(([0-9]+)\) %}~',
                    $line, 
                    $matchStartTag
                )
            ) {
                $skipLoop = true;
                continue;
            }

            if (preg_match('~{% for \$([a-zA-Z0-9_]+) in (.*?) %}~',
                $line, $matchStartTag)
            ) {
                // add loop id to the start tag
                $updatedLoopStartTag = '{% for $' . $matchStartTag[1] . ' in ' . 
                    $matchStartTag[2] . " ($counter) %}";

                // we only update un-nested loop
                if (empty($currentStartTags)) {
                    $this->content[$i] = str_replace(
                        $matchStartTag[0],
                        $updatedLoopStartTag,
                        $this->content[$i]
                    );
                }

                $loopStartTag[$counter] = [
                    'tag' => $updatedLoopStartTag,
                    'value' => $matchStartTag[1],
                    'expression' => $matchStartTag[2],
                    'id' => $counter,
                    'line' => $i,
                    'is_nested' => (!empty($currentStartTags))
                ];

                $currentStartTags[] = $counter;

                // initialize the value variable (the iterator)
                if (!isset($this->data[$matchStartTag[1]])) {
                    $this->data[$matchStartTag[1]] = '';
                }

                $counter += 1;

                $skipLoop = false;
            }
            
            if (!$skipLoop && preg_match('~{% break \((.*?)\) %}~',
                $line, $matchBreakTag)
            ) {
                // add loop id to the break tag
                $updatedBreakTag = '{% break (' . $matchBreakTag[1] . ') <' 
                    . end($currentStartTags) . '> %}';

                $this->content[$i] = str_replace(
                    $matchBreakTag[0],
                    $updatedBreakTag,
                    $this->content[$i]
                );

                $breakLoopTag[end($currentStartTags)][] = [
                    'tag' => $updatedBreakTag,
                    'expression' => $matchBreakTag[1],
                    'id' => end($currentStartTags),
                    'line' => $i
                ];
            }
            
            if (!$skipLoop && preg_match('~{% continue \((.*?)\) %}~',
                $line, $matchContinueTag)
            ) {
                // add loop id to the continue tag
                $updatedContinueTag = '{% continue (' . 
                    $matchContinueTag[1] . ') <' 
                    . end($currentStartTags) . '> %}';

                $this->content[$i] = str_replace(
                    $matchContinueTag[0],
                    $updatedContinueTag,
                    $this->content[$i]
                );

                $continueLoopTag[end($currentStartTags)][] = [
                    'tag' => $updatedContinueTag,
                    'expression' => $matchContinueTag[1],
                    'id' => end($currentStartTags),
                    'line' => $i
                ];
            }

            if (strpos($line, '{% end_for %}') !== false) {
                if (!isset($loopStartTag[end($currentStartTags)])) {
                    throw new TemplateParsingException(
                        "'end_for' used without an loop " .
                        "in template [{$this->template}]"
                    );
                }

                $endingTag = "{% end_for " . end($currentStartTags) . " %}";
                
                $this->content[$i] = str_replace(
                    '{% end_for %}',
                    $endingTag,
                    $line
                );

                $line = str_replace(
                    '{% end_for %}',
                    $endingTag,
                    $line
                );
            }
            
            if (!$skipLoop && preg_match('~{% end_for ([0-9]+) %}~', 
                $line, $matchEndTag)
            ) {
                $endingTag = $matchEndTag[0];

                $loopEndTag[end($currentStartTags)] = [
                    'tag' => $endingTag,
                    'id' => end($currentStartTags),
                    'line' => $i
                ];

                // 'break' and 'continue' outside condition block 
                if (!isset($loopStartTag[end($currentStartTags)]) &&
                    isset($breakLoopTag[end($currentStartTags)])
                ) {
                    throw new TemplateParsingException(
                        "'break' statement was used outside loop " .
                        "in template [{$this->template}]"
                    );
                }
                
                if (!isset($loopStartTag[end($currentStartTags)]) &&
                    isset($continueLoopTag[end($currentStartTags)])
                ) {
                    throw new TemplateParsingException(
                        "'continue' statement was used outside loop " .
                        "in template [{$this->template}]"
                    );
                }

                if (
                    isset($loopStartTag[end($currentStartTags)]) &&
                    isset($loopEndTag[end($currentStartTags)])
                ) {
                    $breakLoop = [];

                    if (isset($breakLoopTag[end($currentStartTags)])) {
                        $breakLoop = $breakLoopTag[end($currentStartTags)];
                    }

                    $continueLoop = [];

                    if (isset($continueLoopTag[end($currentStartTags)])) {
                        $continueLoop = $continueLoopTag[
                            end($currentStartTags)
                        ];
                    }

                    if (!$loopStartTag[end($currentStartTags)]['is_nested']) {
                        $this->loops[] = [
                            'start' => $loopStartTag[end($currentStartTags)],
                            'break' => $breakLoop,
                            'continue' => $continueLoop,
                            'end' => $loopEndTag[end($currentStartTags)],
                        ];
                    } else {
                        // in case of nested loops , we return the normal
                        // 'end_for' , so no confusing happen
                        $this->content[$i] = str_replace(
                            $endingTag,
                            '{% end_for %}',
                            $line
                        );
                    }

                    // reset 'break' and 'continue'
                    $breakLoopTag = [];
                    $continueLoopTag = [];
                }
                
                if ((count($currentStartTags) - 1) >= 0) {
                    unset($currentStartTags[count($currentStartTags) - 1]);
                    $currentStartTags = array_values($currentStartTags);
                }
            }
        }

        // loops open/close tags aren't matched , throw exception
        if (count($loopStartTag) != count($loopEndTag)) {
            if (isset($loopStartTag[0])) {
                throw new TemplateParsingException(
                    "Missing 'end_for' tag for an loop " .
                    "in template [{$this->template}]"
                );
            } else {
                throw new TemplateParsingException(
                    "'end_for' used without an loop " .
                    "in template [{$this->template}]"
                );
            }
        }

        // 'break' and 'continue' outside loop block 
        if (count($loopStartTag) == 0 &&
            count($loopEndTag) == 0 &&
            count($breakLoopTag) != 0
        ) {
            throw new TemplateParsingException(
                "'break' statement was used outside loop " .
                "in template [{$this->template}]"
            );
        }
        
        if (count($loopStartTag) == 0 &&
            count($loopEndTag) == 0 &&
            count($continueLoopTag) != 0
        ) {
            throw new TemplateParsingException(
                "'continue' statement was used outside loop " .
                "in template [{$this->template}]"
            );
        }
    }

    /**
     * Execute loop blocks.
     * 
     * @return void
     */
    private function executeLoops()
    {
        foreach ($this->loops as $id => $loop) {
            if ($loop['start']['is_nested']) {
                continue;
            }
            
            // first we check if the loop block was already processed
            if (
                strpos(
                    $this->content[$loop['start']['line']],
                    '{% for'   
                ) === false
            ) {
                continue;
            }

            // continue if the loop already has body
            if (isset($loop['body']) && !empty($loop['body'])) {
                continue;
            }

            // prepare the expression , we convert the loop expression
            // to a group of items , so we can start looping
            $group = $this->getLoopIterationGroup($loop['start']['expression']);

            $loopBlockContent = [];
            foreach ($group as $item) {
                $this->data[$loop['start']['value']] = $item;
                
                if ($this->breakIsResolved($loop)) {
                    break 1;
                }

                if ($this->continueIsResolved($loop)) {
                    continue 1;
                }

                // process conditions in loops                
                $loopBody = $this->conditionsParser->parse(
                    $this->extractLoopBody($loop),
                    $this->data
                );

                $loopBlockContent = array_merge(
                    $loopBlockContent,
                    $loopBody
                );
            }

            $this->loops[$id]['body'] = $loopBlockContent;
        }
    }

    /**
     * Update the content array with the loops content.
     * 
     * @return void
     */
    private function updateContent()
    {
        $loopId = 0;
        $inLoopBody = false;
        $updatedContent = [];

        // here we delete all the stuff between the loop 'start tag' and the 
        // loop 'end tag' , and the tags it self , then we append the loop's
        // content in place , finally we update the content
        foreach ($this->content as $i => $line) {
            if (!$inLoopBody && 
                preg_match('~{% for \$(.*?) in (.*?) \(([0-9]+)\) %}~',
                $line, $matchStartLoop)
            ) {
                $loopId = $this->getLoopByStartTag($matchStartLoop[0]);
                $inLoopBody = true;
                
                // save line before the loop's start tag
                $lineParts = explode(
                    $this->loops[$loopId]['start']['tag'],
                    $this->content[$i]
                );
                
                $updatedContent[] = $lineParts[0];
                
                continue;
            }
            
            if ($inLoopBody &&
                strpos($line, $this->loops[$loopId]['end']['tag']) !== false
            ) {
                // save line after the loop's end tag
                $lineParts = explode(
                    $this->loops[$loopId]['end']['tag'],
                    $this->content[$i]
                );
                
                $updatedContent = array_merge(
                    $updatedContent,
                    $this->loops[$loopId]['body']
                );

                $updatedContent[] = $lineParts[1];

                // delete this loop , since it's no longer needed
                unset($this->loops[$loopId]);

                $inLoopBody = false;
                continue;
            }

            if ($inLoopBody) {
                $this->content[$i] = '';
            } else {
                $updatedContent[] = $line;
            }
        }

        $this->content = $updatedContent;
    }

    /**
     * Check that the content has no more loops to handle.
     * 
     * @return void
     */
    private function noMoreLoops()
    {
        foreach ($this->content as $line) {
            if (strpos($line, '{% for') !== false ||
                strpos($line, '{% break') !== false ||
                strpos($line, '{% continue') !== false ||
                strpos($line, '{% end_for') !== false
            ) {
                return false;
            }
        }

        return true;    
    }
    
    /**
     * Remove loop values tags.
     * 
     * @return void
     */
    private function removeLoopValueTags()
    {
        foreach ($this->content as $i => $line) {
            $this->content[$i] = preg_replace(
                '~<% (.*?)=(.*?) %>~',
                '',
                $this->content[$i]
            );
        }
    }

    /**
     * Parse loops in a template.
     * 
     * @param array $content
     * @param array &$data
     * @return array
     */
    final public function parse($content, &$data)
    {
        $this->content = $content;
        $this->data = $data;
        
        $this->conditionsParser->template = $this->template;

        while (!$this->noMoreLoops()) {
            $this->handleNestedInlineLoops();
            $this->executeInlineLoops();
            
            $this->handleNestedLoops();
            $this->executeLoops();

            $this->updateContent();
        }

        $this->removeLoopValueTags();

        $data = $this->data;

        return $this->content;
    }
}