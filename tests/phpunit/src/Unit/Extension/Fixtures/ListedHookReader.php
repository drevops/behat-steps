<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\Reader\EnvironmentReader;

/**
 * Environment reader offering the hooks it was built with.
 */
class ListedHookReader implements EnvironmentReader {

  /**
   * Builds the reader.
   *
   * @param array<int, \Behat\Testwork\Call\Callee> $callees
   *   The hooks to offer.
   */
  public function __construct(protected array $callees) {}

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
    return $this->callees;
  }

}
