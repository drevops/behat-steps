<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use DrevOps\BehatSteps\Steps\Drupal\BatchTrait;
use DrevOps\BehatSteps\Steps\Drupal\CacheTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
use DrevOps\BehatSteps\Steps\Drupal\EntityTrait;
use DrevOps\BehatSteps\Steps\Drupal\LanguageTrait;
use DrevOps\BehatSteps\Steps\Drupal\TaxonomyTrait;
use DrevOps\BehatSteps\Steps\Drupal\UserTrait;
use DrevOps\BehatSteps\Steps\Generic\BasicAuthTrait;
use DrevOps\BehatSteps\Steps\Generic\ElementTrait;
use DrevOps\BehatSteps\Steps\Generic\FieldTrait;
use DrevOps\BehatSteps\Steps\Generic\LinkTrait;
use DrevOps\BehatSteps\Steps\Generic\MessageTrait;
use DrevOps\BehatSteps\Steps\Generic\PathTrait;
use DrevOps\BehatSteps\Steps\Generic\RegionTrait;
use DrevOps\BehatSteps\Steps\Generic\ResponseTrait;
use DrevOps\BehatSteps\Steps\Generic\TableTrait;
use DrevOps\BehatSteps\Steps\Generic\WaitTrait;

/**
 * Zero-config context carrying a curated slice of the vocabulary.
 *
 * Registering this context in a suite is enough to write features without
 * writing any PHP. It defines no steps of its own: it is 'RawContext' plus the
 * traits that add vocabulary and nothing else.
 *
 * Traits whose hooks change a scenario's outcome rather than add vocabulary
 * are deliberately absent - Watchdog fails a scenario on a logged PHP error,
 * Javascript on a console error, Accessibility runs axe scans, BigPipe inserts
 * per-step waits, Diagnostics rewrites failure messages. A project opts into
 * those by composing its own context from 'RawContext'.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\RawContext
 */
class DrupalContext extends RawContext {

  use BasicAuthTrait;
  use BatchTrait;
  use CacheTrait;
  use ContentTrait;
  use ElementTrait;
  use EntityTrait;
  use FieldTrait;
  use LanguageTrait;
  use LinkTrait;
  use MessageTrait;
  use PathTrait;
  use RegionTrait;
  use ResponseTrait;
  use TableTrait;
  use TaxonomyTrait;
  use UserTrait;
  use WaitTrait;

}
