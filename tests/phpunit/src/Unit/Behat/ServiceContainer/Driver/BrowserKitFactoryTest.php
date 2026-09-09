<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\ServiceContainer\Driver;

use Drupal\Tests\DrupalTestBrowser;
use Behat\Mink\Driver\BrowserKitDriver;
use DrevOps\BehatSteps\Behat\ServiceContainer\Driver\BrowserKitFactory;
use DrevOps\BehatSteps\Driver\Exception\BootstrapException;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableBrowserKitFactory;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Tests the driver definition built for the 'browserkit_http' session.
 */
#[CoversClass(BrowserKitFactory::class)]
class BrowserKitFactoryTest extends UnitTestCase {

  /**
   * A directory holding no Drupal installation.
   */
  protected const NO_DRUPAL_DIR = __DIR__;

  public function testTheFactoryClaimsMinkBrowserKitName(): void {
    $this->assertSame('browserkit_http', (new BrowserKitFactory())->getDriverName());
  }

  public function testTheDriverRunsNoJavascript(): void {
    $this->assertFalse((new BrowserKitFactory())->supportsJavascript());
  }

  public function testTheConfigAcceptsGuzzleRequestOptions(): void {
    $builder = new ArrayNodeDefinition('browserkit_http');

    (new BrowserKitFactory())->configure($builder);

    $node = $builder->getNode(TRUE);
    $this->assertInstanceOf(ArrayNode::class, $node);
    $this->assertArrayHasKey('guzzle_request_options', $node->getChildren());
  }

  public function testTheDefinitionDrivesDrupalTestBrowser(): void {
    $definition = $this->createFactory()->buildDriver([]);

    $this->assertSame(BrowserKitDriver::class, $definition->getClass());
    $this->assertSame('%mink.base_url%', $definition->getArgument(1));
    $this->assertSame(DrupalTestBrowser::class, $this->getClientDefinition($definition)->getClass());
  }

  public function testRedirectsAreOffAndCookiesOnByDefault(): void {
    $guzzle = $this->getGuzzleDefinition($this->createFactory()->buildDriver([]));

    $this->assertSame(['allow_redirects' => FALSE, 'cookies' => TRUE], $guzzle->getArgument(0));
  }

  public function testSuppliedRequestOptionsReplaceTheDefaults(): void {
    $guzzle = $this->getGuzzleDefinition($this->createFactory()->buildDriver(['guzzle_request_options' => ['timeout' => 5]]));

    $this->assertSame(['timeout' => 5], $guzzle->getArgument(0));
  }

  public function testMissingDrupalRootIsReported(): void {
    $factory = new TestableBrowserKitFactory();

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage('No Drupal installation found');

    $factory->buildDriver([]);
  }

  public function testRootWithoutTestBrowserIsReported(): void {
    $factory = new TestableBrowserKitFactory();
    $factory->testBrowserLoaded = FALSE;

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage('ships no test browser');

    $this->requireTestBrowser($factory, self::NO_DRUPAL_DIR);
  }

  public function testLoadedTestBrowserIsNotIncludedAgain(): void {
    $this->expectNotToPerformAssertions();

    $this->requireTestBrowser(new TestableBrowserKitFactory(), self::NO_DRUPAL_DIR);
  }

  /**
   * Calls the factory's protected test-browser loader.
   */
  protected function requireTestBrowser(BrowserKitFactory $factory, string $drupal_root): void {
    (new \ReflectionMethod($factory, 'requireTestBrowser'))->invoke($factory, $drupal_root);
  }

  /**
   * Builds a factory answering with a Drupal root and a loaded test browser.
   */
  protected function createFactory(): TestableBrowserKitFactory {
    $factory = new TestableBrowserKitFactory();
    $factory->drupalRoot = '/drupal';

    return $factory;
  }

  /**
   * Returns the test-browser definition the driver is built around.
   */
  protected function getClientDefinition(Definition $driver): Definition {
    $client = $driver->getArgument(0);
    $this->assertInstanceOf(Definition::class, $client);

    return $client;
  }

  /**
   * Returns the Guzzle definition handed to the test browser.
   */
  protected function getGuzzleDefinition(Definition $driver): Definition {
    $guzzle = $this->getClientDefinition($driver)->getMethodCalls()[0][1][0];
    $this->assertInstanceOf(Definition::class, $guzzle);

    return $guzzle;
  }

}
