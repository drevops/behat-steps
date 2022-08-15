<?php

/**
 * @file
 * Feature context for testing Behat-steps for Drupal 7.
 */

use DrevOps\BehatSteps\D7\ContentTrait;
use DrevOps\BehatSteps\D7\EmailTrait;
use DrevOps\BehatSteps\D7\FieldCollectionTrait;
use DrevOps\BehatSteps\D7\FileDownloadTrait;
use DrevOps\BehatSteps\D7\FileTrait;
use DrevOps\BehatSteps\D7\MediaTrait;
use DrevOps\BehatSteps\D7\ParagraphsTrait;
use DrevOps\BehatSteps\D7\TaxonomyTrait;
use DrevOps\BehatSteps\D7\UserTrait;
use DrevOps\BehatSteps\D7\VariableTrait;
use DrevOps\BehatSteps\D7\WatchdogTrait;
use DrevOps\BehatSteps\FieldTrait;
use DrevOps\BehatSteps\KeyboardTrait;
use DrevOps\BehatSteps\LinkTrait;
use DrevOps\BehatSteps\PathTrait;
use DrevOps\BehatSteps\ResponseTrait;
use DrevOps\BehatSteps\VisibilityTrait;
use DrevOps\BehatSteps\WaitTrait;
use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContextD7 extends DrupalContext {

  use ContentTrait;
  use EmailTrait;
  use FieldCollectionTrait;
  use FieldTrait;
  use FileDownloadTrait;
  use FileTrait;
  use KeyboardTrait;
  use LinkTrait;
  use MediaTrait;
  use ParagraphsTrait;
  use PathTrait;
  use ResponseTrait;
  use TaxonomyTrait;
  use UserTrait;
  use VariableTrait;
  use VisibilityTrait;
  use WaitTrait;
  use WatchdogTrait;

  use FeatureContextD7Trait;

}
