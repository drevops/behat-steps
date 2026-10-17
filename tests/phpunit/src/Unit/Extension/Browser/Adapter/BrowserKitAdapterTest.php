<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Adapter;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use DrevOps\BehatSteps\Extension\Browser\Adapter\BrowserKitAdapter;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;

/**
 * Tests the capabilities of a BrowserKit-based browser driver.
 */
#[CoversClass(BrowserKitAdapter::class)]
class BrowserKitAdapterTest extends UnitTestCase {

  public function testSupportsOnlyBrowserKitDrivers(): void {
    $this->assertTrue(BrowserKitAdapter::supports($this->createStub(BrowserKitDriver::class)));
    $this->assertFalse(BrowserKitAdapter::supports($this->createStub(DriverInterface::class)));
  }

  public function testCookieGetAllReturnsOneCookiePerNameForTheCurrentUrl(): void {
    $jar = new CookieJar();
    $jar->set(new Cookie('session', 'root', NULL, '/', 'example.com'));
    $jar->set(new Cookie('theme', 'dark', NULL, '/', 'example.com'));
    // A longer path is more specific, so it wins for a URL below it.
    $jar->set(new Cookie('session', 'page', NULL, '/page', 'example.com'));
    $jar->set(new Cookie('other', 'elsewhere', NULL, '/', 'other.example.org'));

    $adapter = new BrowserKitAdapter($this->createBrowserKitDriver($jar, 'http://example.com/page/edit'));

    $this->assertSame([['name' => 'theme', 'value' => 'dark'], ['name' => 'session', 'value' => 'page']], $adapter->cookieGetAll());
  }

  public function testHttpClientIsTheDriverClient(): void {
    $driver = $this->createBrowserKitDriver(new CookieJar(), 'http://example.com/');

    $this->assertSame($driver->getClient(), (new BrowserKitAdapter($driver))->httpClient());
  }

  public function testRequestHeaderSetIsPassedToTheDriver(): void {
    $driver = $this->createBrowserKitDriver(new CookieJar(), 'http://example.com/');
    $driver->expects($this->once())->method('setRequestHeader')->with('Accept', 'application/json');

    (new BrowserKitAdapter($driver))->requestHeaderSet('Accept', 'application/json');
  }

  /**
   * Create a BrowserKit driver over a cookie jar, at a URL.
   *
   * @param \Symfony\Component\BrowserKit\CookieJar $jar
   *   The cookie jar the driver's client holds.
   * @param string $url
   *   The URL the driver reports as current.
   *
   * @return \Behat\Mink\Driver\BrowserKitDriver<object, object>&\PHPUnit\Framework\MockObject\MockObject
   *   The driver.
   */
  protected function createBrowserKitDriver(CookieJar $jar, string $url): BrowserKitDriver&MockObject {
    $client = $this->createStub(AbstractBrowser::class);
    $client->method('getCookieJar')->willReturn($jar);

    $driver = $this->createMock(BrowserKitDriver::class);
    $driver->method('getClient')->willReturn($client);
    $driver->method('getCurrentUrl')->willReturn($url);

    return $driver;
  }

}
