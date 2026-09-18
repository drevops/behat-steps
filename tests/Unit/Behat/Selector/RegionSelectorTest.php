<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Selector;

use Behat\Mink\Selector\CssSelector;
use Behat\Mink\Selector\SelectorInterface;
use DrevOps\BehatSteps\Behat\Selector\RegionSelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a configured region name resolves to the CSS selector's XPath.
 */
#[CoversClass(RegionSelector::class)]
class RegionSelectorTest extends TestCase {

  /**
   * Region map the selector is constructed with.
   */
  protected const REGIONS = ['Header' => '#header', 'Content' => '#main .region-content'];

  public function testImplementsMinkSelectorInterface(): void {
    $this->assertInstanceOf(SelectorInterface::class, $this->createSelector());
  }

  public function testConfiguredRegionResolvesThroughCssSelector(): void {
    $css = new CssSelector();

    $this->assertSame($css->translateToXPath('#header'), $this->createSelector()->translateToXPath('Header'));
  }

  /**
   * Tests that a locator matching no region is rejected.
   *
   * @param string|array<int, string> $locator
   *   The locator handed to the selector.
   */
  #[DataProvider('dataProviderUnknownRegionThrows')]
  public function testUnknownRegionThrows(string|array $locator): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("region isn't configured!");

    $this->createSelector()->translateToXPath($locator);
  }

  public static function dataProviderUnknownRegionThrows(): \Iterator {
    yield 'name that matches no region' => ['Footer'];
    yield 'array locator' => [['Header']];
  }

  /**
   * Builds a selector over the fixture region map.
   */
  protected function createSelector(): RegionSelector {
    return new RegionSelector(new CssSelector(), self::REGIONS);
  }

}
