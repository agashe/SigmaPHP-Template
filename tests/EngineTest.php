<?php 

use PHPUnit\Framework\TestCase;
use SigmaPHP\Template\Engine;

/**
 * Template Engine Test
 */
class EngineTest extends TestCase
{
    /**
     * @var Engine $engine
     */
    private $engine;

    /**
     * EngineTest SetUp
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // define new instance of the template engine
        $this->engine = new Engine(__DIR__ . '/templates');
    }

    /**
     * Get the expected output for a template.
     *
     * @param string $name
     * @return array
     */
    private function getTemplateResult($name)
    {
        return explode("\n", file_get_contents(__DIR__ . "/html/{$name}.html"));
    }

    /**
     * Get the printed output of a template.
     *
     * @param string $template
     * @param array $variables
     * @return array
     */
    private function renderTemplate($template, $variables = [])
    {
        ob_start();
        
        $this->engine->render($template, $variables);
        
        return explode("\n", ob_get_clean());
    }

    /**
     * Compare multi lines output.
     *
     * @param array $actual
     * @param array $expected
     * @return bool
     */
    private function checkOutput($actual, $expected)
    {
        for ($i = 0;$i < count($actual);$i++) {
            if (!isset($expected[$i]) ||
                trim($actual[$i]) != trim($expected[$i])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Test engine will through exception if the template doesn't exists.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineWillThroughExceptionIfTheTemplateDoesNotExists()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('not_found');
    }

    /**
     * Test basic engine's functionality.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testBasicEngineFunctionality()
    {
        $variables = [
            'test1' => 'TEST #1'
        ];

        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('basic', $variables),
            $this->getTemplateResult('basic')
        ));
    }

    /**
     * Test engine will through exception if the expression contains suspicious 
     * functions like eval...etc.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineWillThroughExceptionIfTheExpressionIsSuspicious()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.expression');
    }

    /**
     * Test defining variables.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testDefiningVariables()
    {
        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('variables'),
            $this->getTemplateResult('variables')
        ));
    }
    
    /**
     * Test extend and include templates.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testExtendAndIncludeTemplates()
    {
        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('extend.child'),
            $this->getTemplateResult('extend')
        ));
    }

    /**
     * Test engine will through exception if the template we try to extend 
     * doesn't exists.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineWillThroughExceptionIfExtendedTemplateNotFound()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.extend');
    }

    /**
     * Test blocks.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testBlocks()
    {
        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('blocks'),
            $this->getTemplateResult('blocks')
        ));
    }

    /**
     * Test engine will through exception if unmatched block's close tag.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineWillThroughExceptionIfUnmatchedBlockCloseTag()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.blocks.close_tag');
    }
   
    /**
     * Test engine will through exception if unmatched block's open tag.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineWillThroughExceptionIfUnmatchedBlockOpenTag()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.blocks.open_tag');
    }
    
    /**
     * Test engine will through exception if unmatched block's tag labels.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineWillThroughExceptionIfUnmatchedBlockTagLabels()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.blocks.tag_labels');
    }
    
    /**
     * Test engine will through exception if unmatched inline block's close tag.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineThroughExceptionIfUnmatchedInlineBlockCloseTag()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.blocks.inline.close_tag');
    }
   
    /**
     * Test engine will through exception if unmatched inline block's open tag.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineThroughExceptionIfUnmatchedInlineBlockOpenTag()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.blocks.inline.open_tag');
    }
    
    /**
     * Test engine will through exception if unmatched inline block's tag 
     * labels.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineThroughExceptionIfUnmatchedInlineBlockTagLabels()
    {
        $this->expectException(RuntimeException::class);
    
        $this->engine->render('invalid.blocks.inline.tag_labels');
    }

    /**
     * Test conditions.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testConditions()
    {
        $variables = [
            'test1' => 'TEST #1'
        ];

        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('conditions', $variables),
            $this->getTemplateResult('conditions')
        ));
    }

    // test exceptions for invalid if, else if, else , end if

    /**
     * Test loops.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testLoops()
    {
        $variables = [
            'items' => [
                'item #1', 
                'item #2',
                'item #3',
            ]
        ];

        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('loops', $variables),
            $this->getTemplateResult('loops')
        ));
    }

    // test exceptions for invalid for, break, continue , end for

    // full
    // weird
    // complex
}