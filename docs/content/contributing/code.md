---
title: Code
sidebar_label: Code
sidebar_position: 1
description: Set up the fixture site, find where a change goes, add a step or a helper, run the tests and the linters and open a pull request, with the rules each part follows.
---

<!-- cspell:ignore MAMP -->

# Contribute code

This page is for you when you change the library's code. It covers the change from start to finish: setting up, finding where the change goes, making it, checking it and sending it. [CONTRIBUTING.md](../../../CONTRIBUTING.md) holds the rules, and each part of this page links the rules that apply to it.

## Before you begin

Make sure you have the following:

- [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy) and [Ahoy](https://github.com/ahoy-cli/ahoy) installed.
- No other web server running on your machine, such as Apache, Nginx or MAMP.
- A checkout of the repository in a directory Docker can share, as [Docker's file sharing settings](https://docs.docker.com/desktop/settings-and-maintenance/settings/#file-sharing) describe.
- Node.js 26.13.0 or newer, to build and test the documentation site on your machine. A change to a step doesn't need it, because `ahoy update-docs` runs in Docker.

## Set up

From the repository root, start Pygmy and build the fixture site:

```bash
pygmy up
ahoy build
```

`ahoy build` installs the dependencies and builds a fixture Drupal site in `build/`, which the unit, kernel and Behat suites all run from. The site's address is `http://<directory>.docker.amazee.io/`, where `<directory>` is the name of your checkout directory. `ahoy info` prints the exact address, and `ahoy --help` lists every command. [Local environment setup](../../../CONTRIBUTING.md#local-environment-setup) has the details.

## Find where the change goes

Each kind of change has its own place:

- A step that works on any site goes in a trait under `src/Steps/Web`, and a step that only means something on a Drupal site goes in a trait under `src/Steps/Drupal`.
- Logic that several traits share goes in a helper trait under `src/Steps/Helper/Web`, or under `src/Steps/Helper/Drupal` when it only means something on a Drupal site.
- Anything a step needs from the site goes on a capability, an interface that a backend in `src/Backend` implements. A step reaches a capability such as `CacheCapabilityInterface` through `$this->backendFor(CacheCapabilityInterface::class)`, as [What a trait needs from the backend](../../../CONTRIBUTING.md#what-a-trait-needs-from-the-backend) explains. A Drupal step trait reaches Drupal only through capabilities, and `ahoy lint` fails one that calls Drupal directly.
- How the library plugs into Behat and Mink lives in the extension layer, `src/Extension`.

The [Architecture](../architecture/README.md) pages explain each layer, and [Layers](../../../CONTRIBUTING.md#layers) lists what each one may reach. [Anatomy of a trait](../architecture/anatomy-of-a-trait.md) shows the shape every trait follows.

## Add or change a step

A step is a public method of a trait, with a `#[Given]`, `#[When]` or `#[Then]` attribute holding its text. `src/WebContext.php` composes the web traits and `src/DrupalContext.php` the Drupal traits. To add a new trait, add 2 `use` statements to its context: an import at the top of the file and 1 inside the class. Give the trait's docblock a `@title` tag, which names the trait's page.

To add a step:

1. Word the step by [the steps format](../../../CONTRIBUTING.md#steps-format), which covers who acts, where each value goes, `Given` for a state, `When` for an action and `Then` with `should`. `ahoy lint-docs` checks the rules that section marks "(lint)".
2. Name its method by [the method naming conventions](../../../CONTRIBUTING.md#method-naming-conventions).
3. Keep its body [thin](../../../CONTRIBUTING.md#thin-step-bodies): it parses its arguments, calls helpers and guards on what they return. When it fails, it throws 1 of the [exception types](../../../CONTRIBUTING.md#exception-types), with a message worded by [Failure messages](../../../CONTRIBUTING.md#failure-messages).
4. Give it a docblock with a summary, an example between `@code` and `@endcode` and, after the example, an `@actor:person` or `@actor:system` line. Use `person` when a person does or sees the step through the browser, a terminal or an HTTP client, and `system` otherwise. `ahoy update-docs` generates the trait's page and `STEPS.md` from the docblock, and [Anatomy of a trait](../architecture/anatomy-of-a-trait.md) shows a complete one.
5. Add a scenario that runs the step to the trait's feature file in `tests/behat/features`. The file is named after the trait: `cookie.feature` for `CookieTrait` and `drupal_big_pipe.feature` for `Drupal\BigPipeTrait`. Give the scenario a descriptive tag, such as `@datetime`, so `ahoy test-bdd -- --tags=datetime` runs it on its own. Tag it `@javascript` too when it needs a real browser. The pages and site configuration a scenario runs against are fixtures, which [Static fixtures](../../../CONTRIBUTING.md#static-fixtures) and [Updating fixture site](../../../CONTRIBUTING.md#updating-fixture-site) cover. `StepScenarioCoverageTest` in the unit suite fails any step that no scenario runs, as [Behat tests](../../../CONTRIBUTING.md#behat-tests) explains.
6. To check how the step fails, add a negative scenario tagged `@test-trait:` and the trait's name, such as `@test-trait:CookieTrait` or `@test-trait:Drupal\CacheTrait`. It runs the step in a nested Behat run and checks its failure message, as [Behat harness conventions](../../../CONTRIBUTING.md#behat-harness-conventions) describes.
7. Run `ahoy update-docs`. The trait's page under `docs/content/web-steps/` or `docs/content/drupal-steps/`, `STEPS.md` and `HELPERS.md` change to match the code, and a new trait gets a page of its own.
8. Commit the regenerated files with your change. `ahoy lint-docs` fails when the generated pages are out of date.

A step's text is public API, so renaming a step is a breaking change that waits for the next major release, as [Public API and versioning](../architecture/versioning.md) describes.

## Add or change a helper

A helper is a public trait method that isn't a step, a hook or a transform. Projects call helpers from their own steps. A helper needs a name with a verb and a docblock summary. `ahoy update-docs` and `ahoy lint-docs` both stop on a public helper with no summary, unless its docblock marks it `@internal`. A method that only the trait's own machinery needs stays protected. [The helper API](../../../CONTRIBUTING.md#the-helper-api) has the rules, and `ahoy update-docs` writes the change to `HELPERS.md`, where reviewers see it.

Helpers are public API too, so renaming or removing one is a breaking change that waits for the next major release.

## Run the tests

Run the suites from the repository root:

```bash
ahoy test-unit               # The unit suite
ahoy test-kernel             # The kernel suite
ahoy test-coverage           # Unit and kernel, with coverage
ahoy test-bdd                # Every Behat scenario
ahoy test-bdd -- --tags=wip  # The scenarios tagged @wip

# The scenarios of 1 feature file
ahoy test-bdd /app/tests/behat/features/cookie.feature

# 1 feature file, with coverage
ahoy test-bdd-coverage /app/tests/behat/features/cookie.feature

# A trait's coverage and missed lines
ahoy cli "php scripts/check-coverage.php CookieTrait"
```

Behat runs inside the container from the fixture site, so pass a feature file by its container path under `/app/`. A test's path mirrors its subject's path: `src/Steps/Helper/Web/StringTrait.php` is tested by `tests/phpunit/src/Unit/Steps/Helper/Web/StringTraitTest.php`. Codecov's `codecov/patch` and `codecov/project` checks are required, so a code path no test reaches needs a test rather than a coverage marker. CI collects coverage on 2 legs, both on Behat 3: 1 that runs the browser steps through Selenium and 1 through headless Chrome. Locally, `scripts/check-coverage.php` reads the coverage of the last `ahoy test-bdd-coverage` run, nested `@test-trait` runs included. [Running tests](../../../CONTRIBUTING.md#running-tests) and [Coverage markers](../../../CONTRIBUTING.md#coverage-markers) cover the details.

## Run the linters

Run the linters from the repository root:

```bash
ahoy lint       # The code checks
ahoy lint-fix   # Fix what the linters can fix by themselves
ahoy lint-docs  # The step rules and the generated pages
```

`ahoy lint` leaves out `ahoy lint-docs`, and CI's Lint job runs both. Run both before you push. [Lint](../../../CONTRIBUTING.md#lint) lists what `ahoy lint` runs, and [Settled style questions](../../../CONTRIBUTING.md#settled-style-questions) answers the style questions a reviewer would otherwise ask.

## Check the architecture docs

A change to a layer, a backend, a capability, the extension's wiring or the scenario lifecycle leaves the [architecture pages](../architecture/README.md) and their diagrams out of date. The `update-architecture-docs` skill redraws them from the code, so ask an AI agent to "update architecture docs" before you open the pull request. After a change that isn't structural, the skill leaves every page as it is. Without an agent, edit the pages by hand and redraw a diagram in draw.io, as [Diagram sources](../architecture/README.md#diagram-sources) describes.

## Open a pull request

Send the pull request against `main`, which holds the 4.x line, or against `3.x` for a fix to the 3.x LTS release. CI runs 3 jobs:

- The test matrix runs the unit, kernel and Behat suites across PHP, Drupal and Behat versions.
- The Lint job checks the code and the generated pages.
- The Docs job builds and tests this site.

Codecov then reports `codecov/patch` and `codecov/project` from the 2 coverage legs. [Continuous integration](../../../CONTRIBUTING.md#continuous-integration) explains each leg. To change these docs as well, follow [Documentation](documentation.md).

Confirm that every check on the pull request passes, `codecov/patch` and `codecov/project` included.
