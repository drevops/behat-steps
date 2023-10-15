<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Behat+steps&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Behat steps logo"></a>
</p>

<h1 align="center">Behat steps</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/pulls)
[![CircleCI](https://dl.circleci.com/status-badge/img/gh/drevops/behat-steps.svg?style=shield)](https://dl.circleci.com/status-badge/redirect/gh/drevops/behat-steps)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/behat-steps)
![LICENSE](https://img.shields.io/github/license/drevops/behat-steps)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Total Downloads](https://poser.pugx.org/drevops/behat-steps/downloads)](https://packagist.org/packages/drevops/behat-steps)

</div>

---

<p align="center"> Collection of Behat steps for Drupal
    <br>
</p>

# Why traits?

Usually, such packages implement own Drupal driver with several contexts,
service containers and a lot of other useful architectural structures.
But for this simple library, using traits helps to lower entry barrier for
usage,
maintenance and support.
This package may later be refactored to use proper architecture.

# Installation

`composer require --dev drevops/behat-steps:^2`

For Drupal 7 support:

`composer require --dev drevops/behat-steps:^1`

# Usage

Add required traits
to `FeatureContext.php` ([example](tests/behat/bootstrap/FeatureContext.php)):

```
<?php

use Drupal\DrupalExtension\Context\DrupalContext;
use DrevOps\BehatSteps\ContentTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use ContentTrait;

}
```

## Exceptions

- `\Exception` is thrown for all assertions.
- `\RuntimeException` is thrown for any unfulfilled requirements within a step.

## Available steps

Use `behat -d l` to list all available step definitions.

There are also several pre and post scenario hooks that perform data alterations
and cleanup.

### Skipping before scenario hooks

Some traits provide `beforeScenario` hook implementations. These can be disabled
by adding `behat-steps-skip:METHOD_NAME` tag to your test.

For example, to skip `beforeScenario` hook from `JsTrait`, add
`@behat-steps-skip:jsBeforeScenarioInit` tag to the feature.

## Development

### Local environment setup

- Make sure that you have latest versions of all required software installed:
  - [Docker](https://www.docker.com/)
  - [Pygmy](https://github.com/pygmystack/pygmy)
  - [Ahoy](https://github.com/ahoy-cli/ahoy)
- Make sure that all local web development services are shut down (Apache/Nginx,
  Mysql, MAMP etc).
- Checkout project repository (in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control)).
- `pygmy up`
- `ahoy build` for Drupal 10 build or `DRUPAL_VERSION=9 ahoy build` for Drupal 9.
- Access built site at http://behat-steps.docker.amazee.io/

To develop for another Drupal version, run `ahoy build` again.

Use `ahoy --help` to see the list of available commands.

### Apple M1 adjustments

Copy `default.docker-compose.override.yml` to `docker-compose.override.yml`.

### Behat tests

After every `ahoy build`, a new installation of Drupal is created in `build`
directory.
This project uses fixture Drupal sites (sites with pre-defined configuration)
in order to simplify testing (i.e., the test does not create a content type
but rather uses a content type created from configuration during site
installation).

- Run all tests: `ahoy test-bdd`
- Run all scenarios in specific feature file: `ahoy test-bdd path/to/file`
- Run all scenarios tagged with `@wip` tag: `ahoy test-bdd -- --tags=wip`
- Tests tagged with `@d9` **or** `@d10` will be run **only** for Drupal 9 or
  Drupal 10 respectively.
- Tests tagged with `@d9` **and** `@d10` are agnostic to Drupal version and will
  run for **all** versions.

To debug tests from CLI:

- `ahoy debug`
- Set breakpoint and run tests - your IDE will pickup incoming debug connection.

To update fixtures:

- Make required changes in the installed fixture site
- Run `ahoy drush cex -y`
- Run `ahoy update-fixtures` for Drupal 10
  or `DRUPAL_VERSION=9 ahoy update-fixtures` for Drupal 9 to copy configuration
  changes from build directory to the fixtures directory.
