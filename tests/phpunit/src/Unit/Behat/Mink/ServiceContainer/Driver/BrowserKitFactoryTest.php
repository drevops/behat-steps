<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Mink\ServiceContainer\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the 'browserkit_http' driver built on the shared transport.
 */
#[CoversClass(BrowserKitFactory::class)]
class BrowserKitFactoryTest extends UnitTestCase {

  public function testTheFactoryClaimsMinkBrowserKitName(): void {
    $this->assertSame('browserkit_http', (new BrowserKitFactory())->getDriverName());
  }

  public function testTheDriverRunsNoJavascript(): void {
    $this->assertFalse((new BrowserKitFactory())->supportsJavascript());
  }

  public function testTheConfigKeepsMinkOptions(): void {
    $children = $this->buildConfigTree()->getChildren();

    $this->assertArrayHasKey('http_client_parameters', $children);
  }

  public function testTheDriverRunsOnHttpBrowserOverTheSharedTransport(): void {
    $driver = (new BrowserKitFactory())->buildDriver([]);

    $this->assertSame(BrowserKitDriver::class, $driver->getClass());
    $this->assertSame('%mink.base_url%', $driver->getArgument(1));

    $browser = $driver->getArgument(0);
    $this->assertInstanceOf(Definition::class, $browser);
    $this->assertSame(HttpBrowser::class, $browser->getClass());

    $transport = $browser->getArgument(0);
    $this->assertInstanceOf(Reference::class, $transport);
    $this->assertSame(BrowserKitFactory::TRANSPORT_SERVICE, (string) $transport);
  }

  /**
   * Tests the options recorded from the sessions the factory built.
   *
   * @param array<int, array<string, mixed>> $sessions
   *   The driver configuration of each session built.
   * @param array<string, mixed> $expected
   *   The options expected back.
   */
  #[DataProvider('dataProviderClientOptionsAreReadFromTheSessions')]
  public function testClientOptionsAreReadFromTheSessions(array $sessions, array $expected): void {
    $factory = new BrowserKitFactory();

    foreach ($sessions as $session) {
      $factory->buildDriver($session);
    }

    $this->assertSame($expected, $factory->getClientOptions());
  }

  public static function dataProviderClientOptionsAreReadFromTheSessions(): \Iterator {
    yield 'no session built' => [[], []];
    yield 'a session without options' => [[[]], []];
    yield 'a session with options' => [
      [['http_client_parameters' => ['verify_peer' => FALSE, 'timeout' => 30]]],
      ['timeout' => 30, 'verify_peer' => FALSE],
    ];
    yield 'sessions with the same options in a different order' => [
      [
        ['http_client_parameters' => ['timeout' => 30, 'headers' => ['X-B' => '2', 'X-A' => '1']]],
        ['http_client_parameters' => ['headers' => ['X-A' => '1', 'X-B' => '2'], 'timeout' => 30]],
      ],
      ['headers' => ['X-A' => '1', 'X-B' => '2'], 'timeout' => 30],
    ];
  }

  public function testSessionsWithDifferentOptionsAreRejected(): void {
    $factory = new BrowserKitFactory();
    $factory->buildDriver(['http_client_parameters' => ['timeout' => 30]]);
    $factory->buildDriver(['http_client_parameters' => ['timeout' => 60]]);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The 2 "browserkit_http" sessions declare different "http_client_parameters".');

    $factory->getClientOptions();
  }

  /**
   * Builds the options tree the factory declares for a session.
   */
  protected function buildConfigTree(): ArrayNode {
    $builder = new ArrayNodeDefinition('browserkit_http');

    (new BrowserKitFactory())->configure($builder);

    $node = $builder->getNode(TRUE);
    $this->assertInstanceOf(ArrayNode::class, $node);

    return $node;
  }

}
