<p align="center">
  <a href="" rel="noopener"><img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Behat+steps&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Behat steps logo"></a>
</p>

<h1 align="center">Collection of Behat steps for Drupal</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/pulls)
[![CircleCI](https://circleci.com/gh/drevops/behat-steps.svg?style=shield)](https://circleci.com/gh/drevops/behat-steps)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/behat-steps)
![LICENSE](https://img.shields.io/github/license/drevops/behat-steps)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Total Downloads](https://poser.pugx.org/drevops/behat-steps/downloads)](https://packagist.org/packages/drevops/behat-steps)

</div>

---

## Installation

```bash
composer require --dev drevops/behat-steps:^2
```

## Usage

Add required traits
to your `FeatureContext.php` ([example](tests/behat/bootstrap/FeatureContext.php)):

```php
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

Modification of `behat.yml` configuration is not required.

### Exceptions

- `\Exception` is thrown for all assertions.
- `\RuntimeException` is thrown for any unfulfilled requirements within a step.

### Available steps

Use `behat -d l` to list all available step definitions.

There are also several pre- and post-scenario hooks that perform data alterations
and cleanup.

#### Skipping before scenario hooks

Some traits provide `beforeScenario` hook implementations. These can be disabled
by adding `behat-steps-skip:METHOD_NAME` tag to your test.

For example, to skip `beforeScenario` hook from `JsTrait`, add
`@behat-steps-skip:jsBeforeScenarioInit` tag to the feature.

## Development

### Local environment setup

- Install [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy), [Ahoy](https://github.com/ahoy-cli/ahoy) and shut down local web services (Apache/Nginx, MAMP etc)
- Checkout project repository in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control).
- `pygmy up`
- `ahoy build`
- Access built site at http://behat-steps.docker.amazee.io/

Use `ahoy --help` to see the list of available commands.

#### Apple Silicon adjustments

`cp docker-compose.override.default.yml docker-compose.override.yml`

### Running tests

The source code of traits is tested by running Behat tests in the same way they would be run in your project: traits are included into [FeatureContext.php](tests/behat/bootstrap/FeatureContext.php) and then ran on the pre-configured [fixture Drupal site](tests/behat/fixtures/d10) using [test features](tests/behat/features).

Run `ahoy build` to setup a fixture Drupal site in the `build` directory.

```bash
ahoy test-bdd                # Run all tests

ahoy test-bdd path/to/file   # Run all scenarios in specific feature file

ahoy test-bdd -- --tags=wip  # Run all scenarios tagged with `@wip` tag
```

#### Debugging tests

- `ahoy debug`
- Set breakpoint
- Run tests with `ahoy test-bdd` - your IDE will pickup an incoming debug connection

#### Updating fixture site

- Build the fixture site and make the required changes
- `ahoy drush cex -y`
- `ahoy update-fixtures` to copy configuration
  changes from build directory to the fixtures directory
