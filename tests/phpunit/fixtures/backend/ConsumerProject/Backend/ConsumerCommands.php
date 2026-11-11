<?php

declare(strict_types=1);

namespace ConsumerProject\Backend;

use DrevOps\BehatSteps\Backend\Drupal\Drush\Commands;

/**
 * Fixture: a consumer project's subclass of the base Drush commands.
 *
 * Lives outside the 'DrevOps\BehatSteps\Backend' namespace on purpose:
 * 'DrushBackend' takes commands of any name and namespace. It checks only
 * that the nearest commands of this package the class extends are the ones
 * for the detected major.
 */
class ConsumerCommands extends Commands {}
