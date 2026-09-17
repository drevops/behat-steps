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
 * Tests the extension locators a consuming configuration registers.
 */
#[CoversNothing]
class ExtensionLocatorTest extends TestCase {

  /**
   * Tests the class a locator naming the class outright resolves to.
   *
   * @param class-string $locator
   *   The locator as it appears in a Behat configuration.
   */
  #[DataProvider('dataProviderActivateClassName')]
  public function testActivateClassName(string $locator): void {
    $this->assertInstanceOf($locator, (new ExtensionManager([]))->activateExtension($locator));
  }

  public static function dataProviderActivateClassName(): \Iterator {
    yield 'BehatExtension' => [BehatExtension::class];
    yield 'MinkExtension' => [MinkExtension::class];
  }

  /**
   * Tests the class a locator naming only the namespace resolves to.
   *
   * Behat 3 appends 'ServiceContainer' and the last segment plus 'Extension' to
   * a locator that is not a class, so 'A\B\C' reaches
   * 'A\B\C\ServiceContainer\CExtension'. Behat 4 dropped that shorthand and
   * takes the class name only.
   *
   * @param string $locator
   *   The locator as it appears in a Behat configuration.
   * @param class-string $expected
   *   The extension class it resolves to.
   */
  #[DataProvider('dataProviderActivateNamespace')]
  public function testActivateNamespace(string $locator, string $expected): void {
    if (!(new \ReflectionClass(ExtensionManager::class))->hasMethod('guessFullExtensionClassName')) {
      $this->markTestSkipped('The namespace shorthand is a Behat 3 feature.');
    }

    $this->assertInstanceOf($expected, (new ExtensionManager([]))->activateExtension($locator));
  }

  public static function dataProviderActivateNamespace(): \Iterator {
    yield 'BehatExtension' => ['DrevOps\BehatSteps\Behat', BehatExtension::class];
    yield 'MinkExtension' => ['DrevOps\BehatSteps\Behat\Mink', MinkExtension::class];
  }

}
