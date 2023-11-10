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
     * Test invalid templates.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testInvalidTemplates()
    {
        $exceptionsCount = 0;

        $invalidTemplates = [
            'invalid.expression',
            'invalid.extend',
            
            'invalid.blocks.close_tag',
            'invalid.blocks.open_tag',
            'invalid.blocks.tag_labels',
            'invalid.blocks.inline.close_tag',
            'invalid.blocks.inline.open_tag',
            'invalid.blocks.inline.tag_labels',

            'invalid.conditions.close_tag',
            'invalid.conditions.open_tag',
            'invalid.conditions.else_if_tag',
            'invalid.conditions.else_tag',
            'invalid.conditions.inline.close_tag',
            'invalid.conditions.inline.open_tag',
            'invalid.conditions.inline.else_if_tag',
            'invalid.conditions.inline.else_tag',

            'invalid.loops.close_tag',
            'invalid.loops.open_tag',
            'invalid.loops.break_tag',
            'invalid.loops.continue_tag',
            'invalid.loops.inline.close_tag',
            'invalid.loops.inline.open_tag',
            'invalid.loops.inline.break_tag',
            'invalid.loops.inline.continue_tag',
        ];

        foreach ($invalidTemplates as $invalidTemplate) {
            try {
                $this->engine->render($invalidTemplate);
            } catch (\Exception $e) {
                if ($e instanceof \RuntimeException) {
                    $exceptionsCount += 1;
                }
            }
        }

        $this->assertEquals(count($invalidTemplates), $exceptionsCount);
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

    /**
     * Test full processing for a template.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testFullTemplate()
    {
        $variables = [
            'appName' => 'Testing',
            'message' => 'Your app is working as expected',
            'navLinks' => [
                ['name' => 'home', 'url' => '/path/to/home'],
                ['name' => 'contact', 'url' => '/path/to/contact'],
                ['name' => 'about', 'url' => '/path/to/about'],
            ]
        ];
        
        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('full.app', $variables),
            $this->getTemplateResult('app')
        ));
    }

    /**
     * Test weird template syntax.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testWeirdTemplate()
    {
        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('weird'),
            $this->getTemplateResult('weird')
        ));
    }

    /**
     * Test complex template.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testComplexTemplate()
    {
        $variables = [
            'foo' => 'ahmed',
            'boo' => 'ali',
            'items' => [
                'item #1', 
                'item #2',
                'item #3',
            ],
        ];

        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('complex', $variables),
            $this->getTemplateResult('complex')
        ));
    }
}