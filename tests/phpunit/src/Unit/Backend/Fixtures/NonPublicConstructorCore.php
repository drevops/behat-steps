<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;

/**
 * Test fixture: a Core class whose constructor the backend cannot call.
 */
class NonPublicConstructorCore extends Core {

  protected function __construct() {}

}
