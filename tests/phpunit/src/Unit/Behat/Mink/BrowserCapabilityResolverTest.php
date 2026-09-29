<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Mink;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use DMore\ChromeDriver\ChromeDriver;
use DrevOps\BehatSteps\Behat\Mink\Adapter\BrowserKitAdapter;
use DrevOps\BehatSteps\Behat\Mink\Adapter\ChromeAdapter;
use DrevOps\BehatSteps\Behat\Mink\Adapter\Selenium2Adapter;
use DrevOps\BehatSteps\Behat\Mink\BrowserCapabilityResolver;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\HttpClientCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\KeyboardCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\RequestHeaderCapabilityInterface;
use DrevOps\BehatSteps\Tests\Unit\Behat\Mink\Fixtures\AnyDriverAdapter;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests which capability each shipped adapter declares.
 */
#[CoversClass(BrowserCapabilityResolver::class)]
#[CoversClass(BrowserKitAdapter::class)]
#[CoversClass(ChromeAdapter::class)]
#[CoversClass(Selenium2Adapter::class)]
class BrowserCapabilityResolverTest extends UnitTestCase {

  /**
   * Tests that each driver resolves to the adapter speaking for it.
   *
   * @param class-string<\Behat\Mink\Driver\DriverInterface> $driver_class
   *   The Mink driver class to mock.
   * @param class-string $expected
   *   The adapter class the resolver must build.
   */
  #[DataProvider('dataProviderAdapterForDriver')]
  public function testAdapterForDriver(string $driver_class, string $expected): void {
    $this->skipWithoutDriver($driver_class);

    $driver = $this->createMock($driver_class);

    $this->assertInstanceOf($expected, (new BrowserCapabilityResolver())->resolve($driver, CookieCapabilityInterface::class));
  }

  /**
   * Data provider for testAdapterForDriver().
   */
  public static function dataProviderAdapterForDriver(): \Iterator {
    yield 'browserkit' => [BrowserKitDriver::class, BrowserKitAdapter::class];
    yield 'selenium2' => [Selenium2Driver::class, Selenium2Adapter::class];
    yield 'chrome' => [ChromeDriver::class, ChromeAdapter::class];
  }

  /**
   * Tests the capability set each shipped adapter declares.
   *
   * @param class-string<\Behat\Mink\Driver\DriverInterface> $driver_class
   *   The Mink driver class to mock.
   * @param array<int, class-string> $expected
   *   Every capability the driver must provide.
   */
  #[DataProvider('dataProviderDeclaredCapabilities')]
  public function testDeclaredCapabilities(string $driver_class, array $expected): void {
    $this->skipWithoutDriver($driver_class);

    $driver = $this->createMock($driver_class);
    $resolver = new BrowserCapabilityResolver();

    $all = [
      CookieCapabilityInterface::class,
      HttpClientCapabilityInterface::class,
      JavascriptCapabilityInterface::class,
      KeyboardCapabilityInterface::class,
      RequestHeaderCapabilityInterface::class,
    ];

    $actual = array_values(array_filter($all, static fn(string $capability): bool => $resolver->has($driver, $capability)));

    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testDeclaredCapabilities().
   */
  public static function dataProviderDeclaredCapabilities(): \Iterator {
    // A BrowserKit driver is an HTTP client rather than a browser: it carries
    // cookies, lends its client and sets headers, but runs no script.
    yield 'browserkit' => [
      BrowserKitDriver::class,
      [CookieCapabilityInterface::class, HttpClientCapabilityInterface::class, RequestHeaderCapabilityInterface::class],
    ];
    // A WebDriver session cannot add request headers and holds no client.
    yield 'selenium2' => [
      Selenium2Driver::class,
      [CookieCapabilityInterface::class, JavascriptCapabilityInterface::class, KeyboardCapabilityInterface::class],
    ];
    yield 'chrome' => [
      ChromeDriver::class,
      [CookieCapabilityInterface::class, JavascriptCapabilityInterface::class, KeyboardCapabilityInterface::class, RequestHeaderCapabilityInterface::class],
    ];
  }

  /**
   * Tests that an unknown driver provides nothing and raises when resolved.
   */
  public function testUnknownDriverProvidesNothing(): void {
    $driver = $this->createMock(DriverInterface::class);
    $resolver = new BrowserCapabilityResolver();

    $this->assertFalse($resolver->has($driver, CookieCapabilityInterface::class));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No browser capability "%s" is available', CookieCapabilityInterface::class));

    $resolver->resolve($driver, CookieCapabilityInterface::class);
  }

  /**
   * Tests that a driver providing one capability still refuses another.
   */
  public function testResolveRefusesCapabilityTheDriverLacks(): void {
    $driver = $this->createMock(BrowserKitDriver::class);
    $resolver = new BrowserCapabilityResolver();

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No browser capability "%s" is available', JavascriptCapabilityInterface::class));

    $resolver->resolve($driver, JavascriptCapabilityInterface::class);
  }

  /**
   * Tests that a registered adapter outranks the shipped ones.
   */
  public function testRegisteredAdapterTakesPrecedence(): void {
    $driver = $this->createMock(BrowserKitDriver::class);
    $resolver = new BrowserCapabilityResolver();

    $this->assertInstanceOf(BrowserKitAdapter::class, $resolver->resolve($driver, CookieCapabilityInterface::class));

    $resolver->registerAdapter(AnyDriverAdapter::class);

    $this->assertInstanceOf(AnyDriverAdapter::class, $resolver->resolve($driver, CookieCapabilityInterface::class));
    $this->assertTrue($resolver->has($driver, JavascriptCapabilityInterface::class), 'A registered adapter supplies a capability the shipped one lacks.');
  }

  /**
   * Tests that the adapter for a driver is built once and reused.
   */
  public function testAdapterIsReusedForTheSameDriver(): void {
    $driver = $this->createMock(BrowserKitDriver::class);
    $resolver = new BrowserCapabilityResolver();

    $first = $resolver->resolve($driver, CookieCapabilityInterface::class);
    $second = $resolver->resolve($driver, CookieCapabilityInterface::class);

    $this->assertSame($first, $second);
  }

  /**
   * Tests that the Selenium driver still declares the methods Syn needs.
   *
   * The adapter reaches these through reflection because the driver exposes no
   * public equivalent, so an upstream rename has to fail here rather than in a
   * scenario.
   */
  public function testSeleniumStillDeclaresTheReflectedMethods(): void {
    $this->skipWithoutDriver(Selenium2Driver::class);

    $reflection = new \ReflectionClass(Selenium2Driver::class);

    foreach (Selenium2Adapter::SYN_METHODS as $method) {
      $this->assertTrue($reflection->hasMethod($method), sprintf('%s still declares %s().', Selenium2Driver::class, $method));
    }
  }

  /**
   * Skips the test when the Mink driver package is not installed.
   *
   * Both JavaScript drivers are suggested rather than required, and the Chrome
   * extension pins Behat 3, so a Behat 4 install resolves without it. An
   * adapter still loads and reports FALSE for a driver class that is absent,
   * which is what keeps the resolver working; only a test naming the class
   * directly needs the package present.
   *
   * @param string $driver_class
   *   The Mink driver class the test mocks.
   */
  protected function skipWithoutDriver(string $driver_class): void {
    if (!class_exists($driver_class)) {
      $this->markTestSkipped(sprintf('%s is not installed.', $driver_class));
    }
  }

}
