<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Drupal\Drush\Commands;

/**
 * Test fixture: commands the backend cannot build, because they are abstract.
 */
abstract class AbstractCommands extends Commands {}
