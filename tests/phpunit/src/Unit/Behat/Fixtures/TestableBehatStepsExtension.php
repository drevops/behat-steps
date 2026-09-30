<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

/**
 * Extension whose BrowserKit factory reports the Drupal root a test supplies.
 *
 * Behat instantiates an extension by class name with no arguments, so the
 * root defaults to an installation and a test clears it to model a project
 * without Drupal.
 */
class TestableBehatStepsExtension extends BehatStepsExtension {

  /**
   * Root the created factory reports, or NULL for no Drupal installation.
   */
  public ?string $drupalRoot = '/drupal';

  /**
   * {@inheritdoc}
   */
  protected function createBrowserKitFactory(): TestableBrowserKitFactory {
    $factory = new TestableBrowserKitFactory();
    $factory->drupalRoot = $this->drupalRoot;

    return $factory;
  }

}
