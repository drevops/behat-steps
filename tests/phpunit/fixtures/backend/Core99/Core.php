<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core99;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core as BaseCore;

/**
 * Fixture: the version core of a Drupal 99 that does not exist.
 *
 * Sits where 'DrupalBackend' looks for a version core, so the tests select
 * one without this package shipping any.
 */
class Core extends BaseCore {}
