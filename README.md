# Behat Steps
Collection of Behat steps for Drupal 8 and Drupal 7 development.

[![CircleCI](https://circleci.com/gh/integratedexperts/behat-steps.svg?style=shield)](https://circleci.com/gh/integratedexperts/behat-steps)
[![Latest Stable Version](https://poser.pugx.org/integratedexperts/behat-steps/v/stable)](https://packagist.org/packages/integratedexperts/behat-steps)
[![Total Downloads](https://poser.pugx.org/integratedexperts/behat-steps/downloads)](https://packagist.org/packages/integratedexperts/behat-steps)
[![License](https://poser.pugx.org/integratedexperts/behat-steps/license)](https://packagist.org/packages/integratedexperts/behat-steps)

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

## Development

### Local environment setup
- Make sure that you have latest versions of all required software installed:
  - [Docker](https://www.docker.com/)
  - [Pygmy](https://pygmy.readthedocs.io/)
  - [Ahoy](https://github.com/ahoy-cli/ahoy)
- Make sure that all local web development services are shut down (Apache/Nginx, Mysql, MAMP etc).
- Checkout project repository (in one of the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control)).  
- `pygmy up`
- `ahoy build` for Drupal 8 build or `DRUPAL_VERSION=7 ahoy build` for Drupal 7.
- Access built site at http://behat-steps.docker.amazee.io/  

Please note that you will need to rebuild to work on a different Drupal version.

Use `ahoy --help` to see the list of available commands.   

### Behat tests
After every `ahoy build`, a new installation of Drupal is created in `build` directory.
This project uses fixture Drupal sites (sites with pre-defined configuration)
in order to simplify testing (i.e., the test does not create a content type
but rather uses a content type created from configuration during site installation).

- Run all tests: `ahoy test-bdd`
- Run all scenarios in specific feature file: `ahoy test-bdd path/to/file`
- Run all scenarios tagged with `@wip` tag: `ahoy test-bdd -- --tags=wip`
- Tests tagged with `@d7` or `@d8` will be ran for Drupal 7 and Drupal 8 respectively.
- Tests tagged with both `@d7` and `@d8` are agnostic to Drupal version and will run for both versions. 

To debug tests from CLI:
- `ahoy debug`
- Set breakpoint and run tests - your IDE will pickup incoming debug connection.

To update fixtures:
- Make required changes in the install fixture site
- Run `ahoy cli drush cex -y` for Drupal 8 or `ahoy cli drush fua -y` for Drupal 7
- Run `ahoy update-fixtures` for Drupal 8 or `DRUPAL_VERSION=7 ahoy update-fixtures` for Drupal 7 to export configuration changes from build directory to the fixtures directory. 
