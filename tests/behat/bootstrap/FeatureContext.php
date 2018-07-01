<?php

/**
 * @file
 * MYSITE Drupal context for Behat testing.
 */

use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\D7\ContentTrait;
use IntegratedExperts\BehatSteps\D7\DomainTrait;
use IntegratedExperts\BehatSteps\D7\EmailTrait;
use IntegratedExperts\BehatSteps\D7\OverrideTrait;
use IntegratedExperts\BehatSteps\D7\ParagraphsTrait;
use IntegratedExperts\BehatSteps\D7\UserTrait;
use IntegratedExperts\BehatSteps\D7\VariableTrait;
use IntegratedExperts\BehatSteps\D7\WatchdogTrait;
use IntegratedExperts\BehatSteps\D8\MediaTrait;
use IntegratedExperts\BehatSteps\DateTrait;
use IntegratedExperts\BehatSteps\Field;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\LinkTrait;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use ContentTrait;
//  use DomainTrait;
//  use EmailTrait;
//  use MediaTrait;
//  use OverrideTrait;
//  use ParagraphsTrait;
//  use PathTrait;
//  use UserTrait;
//  use VariableTrait;
//  use WatchdogTrait;
//  use DateTrait;
  use FieldTrait;
  use LinkTrait;
  use PathTrait;
//  use ResponseTrait;
}
