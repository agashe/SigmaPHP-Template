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
     * @return string
     */
    private function getTemplateResult($name)
    {
        return file_get_contents(__DIR__ . "/html/{$name}.html");
    }

    /**
     * Test basic engine functionality.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testBasicEngineFunctionality()
    {
        $this->engine->render('basic', [
            'test1' => 'TEST #1'
        ]);

        $this->expectOutputString($this->getTemplateResult('basic'));
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

    // variables
    // extend and include
    // blocks
    // conditions
    // loops
    // full
    // weird
    // complex
}