<p align="center">
  <a href="" rel="noopener"><img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Behat+steps&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Behat steps logo"></a>
</p>

<h1 align="center">A collection of Behat steps for Drupal</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/pulls)
[![CircleCI](https://circleci.com/gh/drevops/behat-steps.svg?style=shield)](https://circleci.com/gh/drevops/behat-steps)
[![codecov](https://codecov.io/gh/drevops/bats-helpers/graph/badge.svg?token=O0ZYROWCCK)](https://codecov.io/gh/drevops/bats-helpers)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/behat-steps)
![LICENSE](https://img.shields.io/github/license/drevops/behat-steps)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Total Downloads](https://poser.pugx.org/drevops/behat-steps/downloads)](https://packagist.org/packages/drevops/behat-steps)

</div>

---

## Available steps

[List of available steps](steps.md)

## Installation

```bash
composer require --dev drevops/behat-steps:^2
```

## Usage

Add required traits to your
`FeatureContext.php` ([example](tests/behat/bootstrap/FeatureContext.php)):

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

Ensure that your [`behat.yml`](behat.yml) has all the required extensions enabled.

### Exceptions

- `\Exception` is thrown for all assertions.
- `\RuntimeException` is thrown for any unfulfilled requirements within a step.

#### Skipping before scenario hooks

Some traits provide `beforeScenario` hook implementations. These can be disabled
by adding `behat-steps-skip:METHOD_NAME` tag to your test.

For example, to skip `beforeScenario` hook from `JsTrait`, add
`@behat-steps-skip:jsBeforeScenarioInit` tag to the feature.

## Development

### Steps Format

A consistent steps format is essential for the readability and maintainability of tests. Follow these guidelines:

### Steps Format

A consistent steps format is essential for the readability and maintainability of tests. Follow these guidelines:

- **General Guidelines**:
  - Avoid using regular expressions to define a step definition. Use tuple format instead for better clarity and maintainability.
  - Use descriptive placeholder names to help users quickly understand the expected value: `content_type` instead of `type`.
  - Use `the following` for tabled content.
  - For anything identified by a property, use `with`: <code>Then the link :link <b>with</b> the title :title should exist</code>
  - Avoid optional words like `(the|a)`. Provide a single form instead to ensure consistency.
  - Omit unnecessary suffixes like `on the page` since it is implied.
  - All method names should begin with the trait name: `userAssertHasRoles()` for `UserTrait`.

- **`Given`**:
  - Defines test prerequisites—conditions or data that must exist before the test runs.
  - Use words like `exists` or `have`.
  - Avoid using `should` or `should not` (these are reserved for assertions).
  - Refrain from using `Given I` (reserved for actions).

- **`When`**:
  - Describes an action and must contain an action verb.
  - Use the format `When I <verb>`.

- **`Then`**:
  - Specifies assertions and expectations.
  - Use `should` and `should not` to clearly indicate assertions.
  - Start the step with the entity being asserted, e.g., `Then the link with a title :title exists`.
  - Avoid using `Then I`.
  - Methods should include the `Assert` prefix, e.g., `userAssertHasRoles()`.

### Local environment setup

Install [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy), [Ahoy](https://github.com/ahoy-cli/ahoy)
and shut down local web services (Apache/Nginx, MAMP etc)

- Checkout project repository in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control).
- `pygmy up`
- `ahoy build`
- Access built site at http://behat-steps.docker.amazee.io/

Use `ahoy --help` to see the list of available commands.

### Running tests

The source code of traits is tested by running Behat tests in the same way they
would be run in your project: traits are included
into [FeatureContext.php](tests/behat/bootstrap/FeatureContext.php)
and then ran on the
pre-configured [fixture Drupal site](tests/behat/fixtures/d10)
using [test features](tests/behat/features).

Run `ahoy build` to setup a fixture Drupal site in the `build` directory.

```bash
ahoy test-bdd                # Run all tests

ahoy test-bdd path/to/file   # Run all scenarios in specific feature file

ahoy test-bdd -- --tags=wip  # Run all scenarios tagged with `@wip` tag
```

#### Debugging tests

- `ahoy debug`
- Set breakpoint
- Run tests with `ahoy test-bdd` - your IDE will pickup an incoming debug
  connection

#### Updating fixture site

- Build the fixture site and make the required changes
- `ahoy drush cex -y`
- `ahoy update-fixtures` to copy configuration
  changes from build directory to the fixtures directory

#### Validating and updating documentation

The [available steps](steps.md) documentation is generated automatically from
the source code.

The [steps format](#steps-format) is validated as well.

```
ahoy update-docs  # Update documentation

ahoy lint-docs    # Check documentation for errors
```

---
_Repository created using https://getscaffold.dev/ project scaffold template_
