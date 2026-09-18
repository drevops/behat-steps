<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\Reader\EnvironmentReader;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeNodeCreate;

/**
 * Environment reader offering one hook whose callable throws.
 *
 * Lets a test drive the branch where the dispatcher collects an exception
 * instead of raising it.
 */
class ThrowingHookReader implements EnvironmentReader {

  /**
   * {@inheritdoc}
   */
  public function supportsEnvironment(Environment $environment): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function readEnvironmentCallees(Environment $environment): array {
    return [new BeforeNodeCreate(NULL, self::fail(...))];
  }

  /**
   * The hook body, which always fails.
   */
  public static function fail(): void {
    throw new \RuntimeException('The hook failed.');
  }

}
