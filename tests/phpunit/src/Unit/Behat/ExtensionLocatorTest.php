<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat;

use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the extension locators a consuming behat.yml registers.
 *
 * Behat resolves a locator as a class name first and, failing that, as a
 * namespace: 'A\B\C' reaches 'A\B\C\ServiceContainer\CExtension'. Both forms
 * are documented, so both are asserted.
 */
#[CoversNothing]
class ExtensionLocatorTest extends TestCase {

  /**
   * Tests the class an activated locator resolves to.
   *
   * @param string $locator
   *   The locator as it appears in a Behat configuration.
   * @param class-string $expected
   *   The extension class it resolves to.
   */
  #[DataProvider('dataProviderActivate')]
  public function testActivate(string $locator, string $expected): void {
    $this->assertInstanceOf($expected, (new ExtensionManager([]))->activateExtension($locator));
  }

  public static function dataProviderActivate(): \Iterator {
    yield 'namespace' => ['DrevOps\BehatSteps\Behat', BehatExtension::class];
    yield 'class name' => [BehatExtension::class, BehatExtension::class];
    yield 'Mink namespace' => ['DrevOps\BehatSteps\Behat\Mink', MinkExtension::class];
    yield 'Mink class name' => [MinkExtension::class, MinkExtension::class];
  }

}
