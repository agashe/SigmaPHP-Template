<?php 

use PHPUnit\Framework\TestCase;
use SigmaPHP\Template\Engine;

require('route_handlers.php');
require('ExampleController.php');
require('ExampleMiddleware.php');
require('ExamplePageNotFoundHandler.php');
require('ExampleSingleActionController.php');

/**
 * Template Engine Test
 */
class EngineTest extends TestCase
{
    /**
     * RouterTest SetUp
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test router can parse static URLs.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function testRouterCanParseStaticURLs()
    {}
}