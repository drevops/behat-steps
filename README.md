# Behat Steps
Collection of Behat steps for Drupal development.

[![CircleCI](https://circleci.com/gh/integratedexperts/behat-steps.svg?style=shield)](https://circleci.com/gh/integratedexperts/behat-steps)

# Why traits?
Usually, such packages implement own Drupal driver with several contexts, service containers and a lot of other useful architectural structures.
But for this simple library, using traits helps to lower entry barrier for usage, maintenance and support. 
This package may later be refactored to use proper architecture. 

# Installation
`composer require --dev integratedexperts/behat-steps`

# Usage
In `FeatureContext.php`:

```
<?php

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
  use DomainTrait;
  use EmailTrait;
  use MediaTrait;
  use OverrideTrait;
  use ParagraphsTrait;
  use PathTrait;
  use UserTrait;
  use VariableTrait;
  use WatchdogTrait;
  use DateTrait;
  use FieldTrait;
  use LinkTrait;
  use PathTrait;
  use ResponseTrait;

}
```
