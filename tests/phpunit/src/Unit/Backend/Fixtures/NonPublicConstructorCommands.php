<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Drupal\Drush\Commands;

/**
 * Test fixture: commands whose constructor the backend cannot call.
 */
class NonPublicConstructorCommands extends Commands {

  /**
   * Constructs a NonPublicConstructorCommands.
   */
  protected function __construct() {}

}
