<?php

namespace SigmaPHP\Template\Parsers;

use SigmaPHP\Template\Exceptions\TemplateParsingException;
use SigmaPHP\Template\Interfaces\ParserInterface;

/**
 * Blocks Parser Class 
 */
class BlocksParser implements ParserInterface
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
     * @var array $blocks
     */
    public $blocks;

    /**
     * Blocks Parser Constructor.
     */
    public function __construct() {
        $this->template = '';
        $this->content = [];
        $this->data = [];
        $this->blocks = [];
    }

    /**
     * Handle inline nested blocks in template. By adding matching labels for
     * the close tags.
     * 
     * @return void
     */
    private function handleNestedInlineBlocks()
    {
        $blocks = [];
        $blocksStartTag = [];
        $blocksEndTag = [];
        $currentStartTags = [];

        foreach ($this->content as $lineNumber => $contentLine) {
            // check only inline blocks
            if ((strpos($contentLine, '{% block') === false) ||
                (strpos($contentLine, '{% end_block') === false)
            ) {
                continue;
            }

            // we convert the inline if statement into a virtual block we do
            // all the normal process then we implode it again into one 
            // line
            $inlineBlock = explode('{%', $contentLine);
            
            // remove first element , since it's always empty
            // but we keep the text so we can append again later
            // hen the process is done
            $textBeforeBlock = $inlineBlock[0];
            unset($inlineBlock[0]);

            // put back the opening tag '{%'
            $inlineBlock = array_map(function ($line) {
                return '{%' . $line;
            }, $inlineBlock);

            foreach ($inlineBlock as $i => $line) {
                if (preg_match(
                    '~{% block ([\"|\']+)([a-zA-Z0-9\.\-\_]+)(\1) %}~',
                    $line, $matchStartTag)
                ) {
                    // throw exception if the block's name is just a number 
                    if (is_numeric($matchStartTag[2])) {
                        throw new TemplateParsingException(
                            "Invalid block's name [{$matchStartTag[2]}] " . 
                            "in template [{$this->template}]"
                        );
                    }
                    
                    $blocksStartTag[] = [
                        'tag' => $matchStartTag[0],
                        'name' => $matchStartTag[2]
                    ];
    
                    $blocks[$matchStartTag[2]] = [
                        'start_tag' => $matchStartTag[0],
                    ];

                    $currentStartTags[] = $matchStartTag[2];
                }

                if (preg_match('~{% end_block ~', $line, $matchEndTag)) {
                    $endingTag = $matchEndTag[0];

                    // in case the 'end_block' tag doesn't have label we add it
                    // else we first check if it's identical to the start tag
                    // so we proceed , or we through exception ! 
                    if (strpos($line, '{% end_block %}') !== false) {
                        $endingTag = "{% end_block '" .
                            end($currentStartTags) . "' %}";      
                            
                        $inlineBlock[$i] = str_replace(
                            '{% end_block %}',
                            $endingTag,
                            $line
                        );
                    }
                    else if (preg_match(
                        '~{% end_block ([\"|\']+){1}' .
                        '([a-zA-Z0-9\.\-\_]+)(\1) %}~', 
                        $line, $matchEndTagWithBlockName)
                    ) {
                        if (
                            $matchEndTagWithBlockName[2] != end(
                                $currentStartTags
                            )
                        ) {
                            throw new TemplateParsingException(
                                "Missing 'end_block' tag for block '" . 
                                end($currentStartTags) . "'"
                            );
                        }

                        $endingTag = $matchEndTagWithBlockName[0];
                    }

                    $blocksEndTag[] = [
                        'tag' => $endingTag,
                    ];
                    
                    $blocks[end($currentStartTags)]['end_tag'] = $endingTag;

                    if ((count($currentStartTags) - 1) >= 0) {
                        unset($currentStartTags[count($currentStartTags) - 1]);
                        $currentStartTags = array_values($currentStartTags);
                    }

                    // save the line again
                    $this->content[$lineNumber] = $textBeforeBlock . 
                        implode('', $inlineBlock);
                }
            }

            // if blocks open/close tags aren't matched , throw exception
            if (count($blocksStartTag) != count($blocksEndTag)) {
                if (isset($blocksStartTag[0])) {
                    throw new TemplateParsingException(
                        "Missing 'end_block' tag for block " . 
                        $blocksStartTag[0]['name']
                    );
                } else {
                    throw new TemplateParsingException(
                        "'end_block' used without block start " .
                        "in template [{$this->template}]"
                    );
                }
            }
        }

        $this->blocks = array_merge($this->blocks, $blocks);
    }
    
    /**
     * Handle nested blocks in template. By adding matching labels for
     * the close tags.
     * 
     * @return void
     */
    private function handleNestedBlocks()
    {
        $blocksStartTag = [];
        $blocksEndTag = [];
        $currentStartTags = [];

        foreach ($this->content as $i => $line) {
            // skip inline blocks
            if ((strpos($line, '{% block') !== false) && 
                (strpos($line, '{% end_block') !== false)
            ) {
                continue;
            }

            if (preg_match(
                '~{% block ([\"|\']+)([a-zA-Z0-9\.\-\_]+)(\1) %}~',
                $line, $matchStartTag))
            {
                // throw exception if the block's name is just a number 
                if (is_numeric($matchStartTag[2])) {
                    throw new TemplateParsingException(
                        "Invalid block's name [{$matchStartTag[2]}] " . 
                        "in template [{$this->template}]"
                    );
                }

                $blocksStartTag[] = [
                    'tag' => $matchStartTag[0],
                    'name' => $matchStartTag[2]
                ];

                $currentStartTags[] = $matchStartTag[2];
            }

            if (preg_match('~{% end_block ~', $line, $matchEndTag)) {
                $endingTag = $matchEndTag[0];

                if (strpos($line, '{% end_block %}') !== false) {
                    $endingTag = "{% end_block '" .
                        end($currentStartTags) . "' %}";
                    
                    $this->content[$i] = str_replace(
                        '{% end_block %}',
                        $endingTag,
                        $line
                    );
                }
                else if (preg_match(
                    '~{% end_block ([\"|\']+){1}([a-zA-Z0-9\.\-\_]+)(\1) %}~', 
                    $line, $matchEndTagWithBlockName)
                ) {
                    if (
                        $matchEndTagWithBlockName[2] != end($currentStartTags)
                    ) {
                        throw new TemplateParsingException(
                            "Missing 'end_block' tag for block '" . 
                            end($currentStartTags) . "'"
                        );
                    }

                    $endingTag = $matchEndTagWithBlockName[0];
                }

                $blocksEndTag[] = [
                    'tag' => $endingTag,
                ];
                
                if ((count($currentStartTags) - 1) >= 0) {
                    unset($currentStartTags[count($currentStartTags) - 1]);
                    $currentStartTags = array_values($currentStartTags);
                }
            }
        }

        // if blocks open/close tags aren't matched , throw exception
        if (count($blocksStartTag) != count($blocksEndTag)) {
            if (isset($blocksStartTag[0])) {
                throw new TemplateParsingException(
                    "Missing 'end_block' tag for block " . 
                    $blocksStartTag[0]['name']
                );
            } else {
                throw new TemplateParsingException(
                    "'end_block' used without block start " .
                    "in template [{$this->template}]"
                );
            }
        }
    }

    /**
     * Extract blocks from template.
     * 
     * @return void
     */
    private function extractBlocks()
    {
        $blocks = [];
        $foundBlocks = [];
        $currentBlock = '';
        $lines = $this->content;

        foreach ($this->content as $line) {
            if (preg_match_all(
                '~{% block ([\"|\']+)([a-zA-Z0-9\.\-\_]+)(\1) %}~',
                $line, $matches)
            ) {
                $foundBlocks = array_merge($foundBlocks, $matches[2]);
            } 
        }

        foreach (array_unique($foundBlocks) as $block) {
            foreach ($lines as $i => $line) {
                // handle inline 
                if ((strpos($line, '{% block') !== false) && 
                    (strpos($line, '{% end_block') !== false) &&
                    preg_match(
                        '~{% block ([\"|\']+)([a-zA-Z0-9\.\-\_]+)(\1) %}~',
                        $line, $match) && ($match[2] == $block)
                ) {

                    // delete old implementation inline or not
                    if (isset($this->blocks[$block]['body'])) {
                        unset($this->blocks[$block]);
                    }
                    elseif (isset($blocks[$block]['body'])) {
                        unset($blocks[$block]);
                    }
                    
                    $blockStartBoundary = preg_quote(
                        $this->blocks[$block]['start_tag']
                    );
                    
                    $blockEndBoundary = preg_quote(
                        $this->blocks[$block]['end_tag']
                    );

                    // extract body
                    if (preg_match(
                        "~$blockStartBoundary(\s*.*?\s*)$blockEndBoundary~",
                        $this->content[$i],
                        $match
                    )) {
                        $this->blocks[$block]['body'] = [$match[1]];
                    }

                    $this->content[$i] =
                        preg_replace(
                            "~$blockStartBoundary(\s*.*?\s*)$blockEndBoundary~",
                            '',
                            $this->content[$i]
                        );
                    
                    $line = $this->content[$i];
                    $lines = $this->content;
                }

                if (empty($currentBlock) && 
                    preg_match(
                        '~{% block ([\"|\']+)([a-zA-Z0-9\.\-\_]+)(\1) %}~',
                        $line, $match) && ($match[2] == $block)
                ) {
                    $blocks[$match[2]] = [
                        'start_tag' => $match[0],
                        'body' => []
                    ];

                    $currentBlock = $match[2];
                }
                
                // extract the content and skip the block tags
                if (!empty($currentBlock)) {
                    $cleanLine = $line;
                    $firstLineIsEmpty = false;

                    if (strpos($line, $blocks[$currentBlock]['start_tag']) 
                        !== false
                    ) {
                        $lineParts = explode(
                            $blocks[$currentBlock]['start_tag'],
                            $line
                        );
                        
                        // the start line will contain the all the text after 
                        // the block's start the tag , NOT BEFORE !!
                        // all the text before the block's start tag
                        // is just normal text to be printed
                        $cleanLine = $lineParts[1];

                        // in case the first line of the block is just empty
                        // line don't append it , in case of inline show block, 
                        // the line will be braked and the useful content will
                        // be hidden
                        if (empty($cleanLine)) {
                            $firstLineIsEmpty = true;
                        }

                        // this line now will only contain all the text BEFORE
                        // the start tag
                        $this->content[$i] = $lineParts[0];
                    }
                    else if (preg_match("~{% end_block '{$currentBlock}' %}~", 
                        $line, $matchEndTag)
                    ) { 
                        $lineParts = explode(
                            $matchEndTag[0],
                            $line
                        );

                        // same as start tag , but here we only take
                        // text before end tag , NOT AFTER !!
                        $cleanLine = $lineParts[0];

                        // this line now will only contain all the text AFTER
                        // the start tag
                        $this->content[$i] = $lineParts[1];
                    }
                    else {                        
                        // remove the block's body from the content
                        $this->content[$i] = '';
                    }

                    if (!$firstLineIsEmpty) {
                        $blocks[$currentBlock]['body'][] = trim($cleanLine);
                    }
                }

                // here we check for the matching closing tag
                if (preg_match("~{% end_block '{$currentBlock}' %}~", 
                    $line, $matchEndTag)
                ) {
                    $blocks[$currentBlock]['end_tag'] = $matchEndTag[0];

                    // in case of 'extend' or 'include' we take the latest
                    // version of the block , which is in the current template
                    // under processing , so we ignore old block implementation
                    // to show block's content from parent template , we simply
                    // remove the block from the current template
                    if (isset($this->blocks[$currentBlock]['body'])) {
                        unset($this->blocks[$currentBlock]);
                    }

                    $currentBlock = '';
                }
            }
        }

        $this->blocks = array_merge($this->blocks, $blocks);
    }

    /**
     * Parse blocks in a template.
     * 
     * @param array $content
     * @param array &$data
     * @return array
     */
    final public function parse($content, &$data)
    {
        $this->content = $content;
        $this->data = $data;

        $this->handleNestedInlineBlocks();
        $this->handleNestedBlocks();
        $this->extractBlocks();

        $data = $this->data;

        return $this->content;
    }
}