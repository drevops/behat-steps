<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Adapter;

use DMore\ChromeDriver\ChromeDriver;
use DMore\ChromeDriver\ChromePage;
use DrevOps\BehatSteps\Extension\Browser\Adapter\ChromeAdapter;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the CDP transport of the Chrome browser driver adapter.
 */
#[CoversClass(ChromeAdapter::class)]
class ChromeAdapterTest extends UnitTestCase {

  public function testClockCommandsGoThroughThePageConnection(): void {
    $page = $this->createMock(ChromePage::class);
    $page->expects($this->once())->method('send')->with('Emulation.setTimezoneOverride', ['timezoneId' => 'Australia/Melbourne'])->willReturn([]);

    (new ChromeAdapter($this->createChromeDriver($page)))->clockSetTimezone('Australia/Melbourne');
  }

  public function testDriverWithoutPageFails(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The Chrome browser driver has no page connection.');

    (new ChromeAdapter($this->createChromeDriver(NULL)))->clockReset();
  }

  /**
   * Create a Chrome browser driver holding a page connection.
   *
   * @param \DMore\ChromeDriver\ChromePage|null $page
   *   The page connection, or NULL for a driver that has not started.
   *
   * @return \DMore\ChromeDriver\ChromeDriver
   *   The driver.
   */
  protected function createChromeDriver(?ChromePage $page): ChromeDriver {
    $driver = $this->createStub(ChromeDriver::class);
    (new \ReflectionProperty(ChromeDriver::class, ChromeAdapter::PAGE_PROPERTY))->setValue($driver, $page);

    return $driver;
  }

}
