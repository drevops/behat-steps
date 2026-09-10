<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory;

/**
 * Exposes the factory's environment lookups so a test can supply them.
 */
class TestableBrowserKitFactory extends BrowserKitFactory {

  /**
   * Root to answer with in place of the one Composer recorded.
   */
  public ?string $drupalRoot = NULL;

  /**
   * Whether the test browser counts as already loaded.
   */
  public bool $testBrowserLoaded = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function resolveDrupalRoot(): ?string {
    return $this->drupalRoot;
  }

  /**
   * {@inheritdoc}
   */
  protected function testBrowserIsLoaded(): bool {
    return $this->testBrowserLoaded;
  }

}
