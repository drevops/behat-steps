<?php

/**
 * @file
 * Feature context for testing Behat-steps.
 */

declare(strict_types=1);

use DrevOps\BehatSteps\BigPipeTrait;
use DrevOps\BehatSteps\BlockTrait;
use DrevOps\BehatSteps\BlockContentTrait;
use DrevOps\BehatSteps\ContentTrait;
use DrevOps\BehatSteps\CookieTrait;
use DrevOps\BehatSteps\DateTrait;
use DrevOps\BehatSteps\DraggableviewsTrait;
use DrevOps\BehatSteps\EckTrait;
use DrevOps\BehatSteps\ElementTrait;
use DrevOps\BehatSteps\EmailTrait;
use DrevOps\BehatSteps\FieldTrait;
use DrevOps\BehatSteps\FileDownloadTrait;
use DrevOps\BehatSteps\FileTrait;
use DrevOps\BehatSteps\KeyboardTrait;
use DrevOps\BehatSteps\LinkTrait;
use DrevOps\BehatSteps\MediaTrait;
use DrevOps\BehatSteps\MenuTrait;
use DrevOps\BehatSteps\MetatagTrait;
use DrevOps\BehatSteps\OverrideTrait;
use DrevOps\BehatSteps\ParagraphsTrait;
use DrevOps\BehatSteps\PathTrait;
use DrevOps\BehatSteps\ResponseTrait;
use DrevOps\BehatSteps\RoleTrait;
use DrevOps\BehatSteps\SearchApiTrait;
use DrevOps\BehatSteps\SelectTrait;
use DrevOps\BehatSteps\TaxonomyTrait;
use DrevOps\BehatSteps\TestmodeTrait;
use DrevOps\BehatSteps\UserTrait;
use DrevOps\BehatSteps\VisibilityTrait;
use DrevOps\BehatSteps\WaitTrait;
use DrevOps\BehatSteps\WatchdogTrait;
use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use BigPipeTrait;
  use BlockTrait;
  use BlockContentTrait;
  use CookieTrait;
  use ContentTrait;
  use CookieTrait;
  use EckTrait;
  use DateTrait;
  use DraggableviewsTrait;
  use EmailTrait;
  use ElementTrait;
  use FieldTrait;
  use FileDownloadTrait;
  use FileTrait;
  use KeyboardTrait;
  use LinkTrait;
  use MediaTrait;
  use MetatagTrait;
  use MenuTrait;
  use OverrideTrait;
  use ParagraphsTrait;
  use PathTrait;
  use ResponseTrait;
  use RoleTrait;
  use SelectTrait;
  use SearchApiTrait;
  use TaxonomyTrait;
  use TestmodeTrait;
  use UserTrait;
  use VisibilityTrait;
  use WatchdogTrait;
  use WaitTrait;

  use FeatureContextTrait;

  /**
   * Override dateNow() method to return a preset value for testing.
   *
   * This cannot be moved to FeatureContextTrait because traits cannot override
   * methods from other traits.
   */
  protected static function dateNow(): int {
    return strtotime('2024-07-15 12:00:00');
  }

}
