<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\DrupalBackend;

/**
 * Test fixture that injects a synthetic Drupal version string.
 *
 * The parent constructor calls 'detectMajorVersion()' which in turn calls
 * 'readVersionConstant()'. Overriding the latter lets tests exercise the
 * malformed-version and sub-11 branches without mutating '\Drupal::VERSION'.
 */
class FakeVersionDrupalBackend extends DrupalBackend {

  /**
   * The version string the next constructor call will report.
   */
  public static string $nextVersion = '11.0.0';

  /**
   * {@inheritdoc}
   */
  protected function readVersionConstant(): string {
    return self::$nextVersion;
  }

}
