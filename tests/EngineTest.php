<?php 

use PHPUnit\Framework\TestCase;
use SigmaPHP\Template\Engine;
use SigmaPHP\Template\Exceptions\CacheDirectoryNotFoundException;

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
     * @param bool $print
     * @return array
     */
    private function renderTemplate($template, $variables = [], $print = false)
    {
        $result = '';

        if ($print) {
            ob_start();
            
            $this->engine->render($template, $variables, true);
            
            $result = ob_get_clean();
        } else {
            $result = $this->engine->render($template, $variables);
        }

        return explode("\n", $result);
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
     * Test invalid templates.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testInvalidTemplates()
    {
        $exceptionsCount = 0;

        $invalidTemplates = [
            'invalid.not_found' => 'TemplateNotFoundException',
            'invalid.statement' => 'InvalidStatementException',
            'invalid.expression' => 'InvalidExpressionException',
            'invalid.extend' => 'TemplateNotFoundException',
            'invalid.directives' => 'UndefinedDirectiveException',
            
            'invalid.blocks.close_tag' => 'TemplateParsingException',
            'invalid.blocks.open_tag' => 'TemplateParsingException',
            'invalid.blocks.tag_labels' => 'TemplateParsingException',

            'invalid.blocks.inline.close_tag' => 'TemplateParsingException',
            'invalid.blocks.inline.open_tag' => 'TemplateParsingException',
            'invalid.blocks.inline.tag_labels' => 'TemplateParsingException',

            'invalid.conditions.close_tag' => 'TemplateParsingException',
            'invalid.conditions.open_tag' => 'TemplateParsingException',
            'invalid.conditions.else_if_tag' => 'TemplateParsingException',
            'invalid.conditions.else_tag' => 'TemplateParsingException',

            'invalid.conditions.inline.close_tag' => 'TemplateParsingException',
            'invalid.conditions.inline.open_tag' => 'TemplateParsingException',
            'invalid.conditions.inline.else_if_tag' =>
                'TemplateParsingException',
            'invalid.conditions.inline.else_tag' => 'TemplateParsingException',

            'invalid.loops.close_tag' => 'TemplateParsingException',
            'invalid.loops.open_tag' => 'TemplateParsingException',
            'invalid.loops.break_tag' => 'TemplateParsingException',
            'invalid.loops.continue_tag' => 'TemplateParsingException',
            'invalid.loops.expression' => 'InvalidExpressionException',

            'invalid.loops.inline.close_tag' => 'TemplateParsingException',
            'invalid.loops.inline.open_tag' => 'TemplateParsingException',
            'invalid.loops.inline.break_tag' => 'TemplateParsingException',
            'invalid.loops.inline.continue_tag' => 'TemplateParsingException',
            'invalid.loops.inline.expression' => 'InvalidExpressionException',
        ];

        foreach ($invalidTemplates as $invalidTemplate => $exceptionType) {
            try {
                $this->engine->render($invalidTemplate);
            } catch (\Exception $e) {
                if ($e instanceof (
                    'SigmaPHP\Template\Exceptions\\' . $exceptionType)
                ) {
                    $exceptionsCount += 1;
                }
            }
        }

        $this->assertEquals(count($invalidTemplates), $exceptionsCount);
    }

    /**
     * Test include and extend by relative path.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testIncludeAndExtendByRelativePath()
    {
        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('./relative.sectionA'),
            $this->getTemplateResult('relative')
        ));
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
     * Test custom directives.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testCustomDirectives()
    {
        $this->engine->registerCustomDirective('add', function (...$numbers) {
            $sum = 0;

            foreach ($numbers as $number) {
                $sum += $number;
            }

            return $sum;
        });

        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('directives'),
            $this->getTemplateResult('directives')
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

    /**
     * Test engine can prints the result.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineCanPrintsTheResult()
    {
        $variables = [
            'test1' => 'TEST #1'
        ];

        $this->assertTrue($this->checkOutput(
            $this->renderTemplate('basic', $variables, true),
            $this->getTemplateResult('basic')
        ));
    }
    
    /**
     * Test engine will through exception if the cache path doesn't exist.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testEngineWillThroughExceptionIfTheCachePathDoesNotExist()
    {
        $this->expectException(CacheDirectoryNotFoundException::class);

        $engine = new Engine(__DIR__ . '/templates', 'fake-cache-dir/');
        $engine->render('variables');
    }

    /**
     * Test save cache.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testSaveCache()
    {
        $engine = new Engine(
            __DIR__ . '/templates',
            __DIR__ . '/cache'
        );
        
        $this->assertTrue($this->checkOutput(
            explode("\n", $engine->render('variables')),
            $this->getTemplateResult('variables')
        ));
    }
   
    /**
     * Test load cache.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testLoadCache()
    {
        $engine = new Engine(
            __DIR__ . '/templates',
            __DIR__ . '/cache'
        );
        
        $this->assertTrue($this->checkOutput(
            explode("\n", $engine->render('variables')),
            $this->getTemplateResult('variables')
        ));
    }
}