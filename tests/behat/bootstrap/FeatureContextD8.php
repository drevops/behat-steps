<?php

/**
 * @file
 * MYSITE Drupal context for Behat testing.
 */

use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\Field;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContextD8 extends DrupalContext {

  use PathTrait;
  use ResponseTrait;

}
