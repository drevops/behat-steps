<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;

/**
 * Test fixture: a Core class the backend cannot build, because it is abstract.
 */
abstract class AbstractCore extends Core {}
