<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Mink\ServiceContainer;

use Behat\MinkExtension\ServiceContainer\Driver\BrowserKitFactory as UpstreamBrowserKitFactory;
use Behat\MinkExtension\ServiceContainer\Driver\DriverFactory;
use Behat\MinkExtension\ServiceContainer\MinkExtension as UpstreamMinkExtension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the driver factory swap and the deprecated 'ajax_timeout' alias.
 */
#[CoversClass(MinkExtension::class)]
class MinkExtensionTest extends UnitTestCase {

  public function testTheExtensionKeepsMinkConfigKey(): void {
    $this->assertSame('mink', (new MinkExtension())->getConfigKey());
  }

  public function testTheBrowserKitDriverIsBuiltByTheFirstPartyFactory(): void {
    $factories = new \ReflectionProperty(UpstreamMinkExtension::class, 'driverFactories');

    $this->assertInstanceOf(UpstreamBrowserKitFactory::class, $factories->getValue(new UpstreamMinkExtension())['browserkit_http']);
    $this->assertInstanceOf(BrowserKitFactory::class, $factories->getValue($this->innerExtension())['browserkit_http']);
  }

  public function testAnotherExtensionCanRegisterItsDriverFactory(): void {
    $extension = new MinkExtension();
    $factory = $this->createMock(DriverFactory::class);
    $factory->method('getDriverName')->willReturn('behat_steps_test');

    $extension->registerDriverFactory($factory);

    $factories = new \ReflectionProperty(UpstreamMinkExtension::class, 'driverFactories');
    $this->assertArrayHasKey('behat_steps_test', $factories->getValue($this->innerExtension($extension)));
  }

  public function testInitializeReachesTheWrappedExtension(): void {
    $manager = new ExtensionManager([]);

    (new MinkExtension())->initialize($manager);

    $this->assertSame([], $manager->getExtensions());
  }

  public function testProcessRegistersTaggedSelectorsThroughTheWrappedExtension(): void {
    $container = new ContainerBuilder();
    $this->load($container, []);
    $container->register('behat_steps.test_selector')->addTag('mink.selector', ['alias' => 'region']);

    (new MinkExtension())->process($container);

    $aliases = [];
    foreach ($container->getDefinition('mink.selectors_handler')->getMethodCalls() as $call) {
      if ($call[0] === 'registerSelector') {
        $aliases[] = $call[1][0];
      }
    }

    $this->assertContains('region', $aliases);
  }

  public function testTheConfigTreeStillCarriesMinkOptions(): void {
    $this->assertArrayHasKey('base_url', $this->buildConfigTree()->getChildren());
  }

  public function testTheConfigTreeAcceptsTheDeprecatedAjaxTimeout(): void {
    $this->assertArrayHasKey('ajax_timeout', $this->buildConfigTree()->getChildren());
  }

  public function testAnAbsentAjaxTimeoutSetsNoParameter(): void {
    $container = new ContainerBuilder();

    $this->load($container, []);

    $this->assertFalse($container->hasParameter(MinkExtension::DEPRECATED_AJAX_TIMEOUT_PARAMETER));
  }

  public function testSuppliedAjaxTimeoutIsCarriedToTheParameter(): void {
    $container = new ContainerBuilder();

    $this->load($container, ['ajax_timeout' => 12]);

    $this->assertSame(12, $container->getParameter(MinkExtension::DEPRECATED_AJAX_TIMEOUT_PARAMETER));
  }

  public function testTheDeprecatedAjaxTimeoutIsMarkedOnTheNode(): void {
    $node = $this->buildConfigTree()->getChildren()['ajax_timeout'];
    $this->assertInstanceOf(BaseNode::class, $node);

    $this->assertTrue($node->isDeprecated());
    $this->assertStringContainsString('behat_steps', $node->getDeprecation('ajax_timeout', 'mink')['message']);
  }

  /**
   * Reads the Mink extension the first-party one delegates to.
   *
   * @param \DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension|null $extension
   *   The extension to read, or NULL to read a freshly built one.
   */
  protected function innerExtension(?MinkExtension $extension = NULL): UpstreamMinkExtension {
    $inner = new \ReflectionProperty(MinkExtension::class, 'inner');
    $value = $inner->getValue($extension ?? new MinkExtension());
    $this->assertInstanceOf(UpstreamMinkExtension::class, $value);

    return $value;
  }

  /**
   * Builds the extension's configuration tree.
   */
  protected function buildConfigTree(): ArrayNode {
    $builder = new ArrayNodeDefinition('mink');

    (new MinkExtension())->configure($builder);

    $node = $builder->getNode(TRUE);
    $this->assertInstanceOf(ArrayNode::class, $node);

    return $node;
  }

  /**
   * Loads the extension over the minimum configuration Mink requires.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container to load into.
   * @param array<string, mixed> $config
   *   Configuration to merge over the minimum.
   */
  protected function load(ContainerBuilder $container, array $config): void {
    (new MinkExtension())->load($container, $config + [
      'base_url' => 'http://example.com',
      'sessions' => [],
    ]);
  }

}
