---
title: Code
sidebar_label: Code
sidebar_position: 1
description: Set up the fixture site, find where a change goes, add a step or a helper, run the tests and the linters and open a pull request, with the rules each part follows.
---

<!-- cspell:ignore MAMP -->

# Contributing code

This page walks through a code change from start to finish: setting up, finding where the change goes, making it, checking it and sending it. [CONTRIBUTING.md](../../../CONTRIBUTING.md) holds the rules themselves, and each part of this page links the ones it touches.

## Before you begin

Make sure you have the following:

- [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy) and [Ahoy](https://github.com/ahoy-cli/ahoy) installed.
- No other web server running on your machine, such as Apache, Nginx or MAMP.
- A checkout of the repository in a directory Docker can share, as [Docker's file sharing settings](https://docs.docker.com/desktop/settings-and-maintenance/settings/#file-sharing) describe.
- Node.js 24.22.0 or newer, to build and test the documentation site, which runs on your machine rather than in Docker. A change to a step doesn't need it: `ahoy update-docs` runs in Docker.

## Set up

From the repository root, start Pygmy and build the fixture site:

```bash
pygmy up
ahoy build
```

`ahoy build` installs the dependencies and builds a fixture Drupal site in `build/`, which the unit, kernel and Behat suites all run against. The site answers at `http://<directory>.docker.amazee.io/`, where `<directory>` is the name of your checkout directory. `ahoy info` prints the exact address, and `ahoy --help` lists every command. [Local environment setup](../../../CONTRIBUTING.md#local-environment-setup) has the details.

## Find where the change goes

Each kind of change has its own place:

- A step that works on any site goes in a trait under `src/Steps/Web`, and a step that only means something on a Drupal site in a trait under `src/Steps/Drupal`.
- Logic that several traits share goes in a helper trait under `src/Steps/Helper/Web`, or under `src/Steps/Helper/Drupal` when it only means something on a Drupal site.
- Anything a step needs from the site goes on a capability, an interface a backend in `src/Backend` implements, such as `CacheCapabilityInterface`, never in the step itself. A step reaches it through `$this->backendFor(CacheCapabilityInterface::class)`, as [What a trait needs from the backend](../../../CONTRIBUTING.md#what-a-trait-needs-from-the-backend) explains. A Drupal step trait reaches Drupal through capabilities and nothing else, and `ahoy lint` fails one that calls Drupal directly.
- How the library plugs into Behat and Mink lives in the extension layer, `src/Extension`.

The [Architecture](../architecture/README.md) pages explain each layer, and [Layers](../../../CONTRIBUTING.md#layers) lists what each one may reach. [Anatomy of a trait](../architecture/anatomy-of-a-trait.md) shows the shape every trait follows.

## Add or change a step

A step is a public method of a trait, with a `#[Given]`, `#[When]` or `#[Then]` attribute holding its text. A new trait is composed by the context for its side, so add it to `src/WebContext.php` for a web trait or `src/DrupalContext.php` for a Drupal trait: an import at the top of the file and a `use` statement inside the class. Its docblock also needs a `@title` tag, which names its page.

To add a step:

1. Word the step by [the steps format](../../../CONTRIBUTING.md#steps-format): who acts, `Given` for a state, `When` for an action, `Then` with `should`, and where each value goes. `ahoy lint-docs` checks the rules that section marks "(lint)".
2. Name its method by [the method naming conventions](../../../CONTRIBUTING.md#method-naming-conventions).
3. Keep its body [thin](../../../CONTRIBUTING.md#thin-step-bodies): it parses its arguments, calls helpers and guards on what they return. When it fails, it throws 1 of the [exception types](../../../CONTRIBUTING.md#exception-types), with a message worded by [Failure messages](../../../CONTRIBUTING.md#failure-messages).
4. Give it a docblock: a summary, an example between `@code` and `@endcode`, and, after the example, an `@actor:person` or `@actor:system` line. It's `person` when a person does or sees the step through the browser, a terminal or an HTTP client, and `system` otherwise. The trait's page and `STEPS.md` are generated from the docblock.
5. Add a scenario that runs the step to the trait's feature file in `tests/behat/features`, named after the trait: `cookie.feature` for `CookieTrait`, and `drupal_big_pipe.feature` for `Drupal\BigPipeTrait`. Give the scenario a descriptive tag, such as `@datetime`, so it can run on its own with `ahoy test-bdd -- --tags=datetime`. `StepScenarioCoverageTest` in the unit suite fails any step that no scenario runs, as [Behat tests](../../../CONTRIBUTING.md#behat-tests) explains. To check how the step fails, add a negative scenario tagged `@test-trait:` and the trait's name, like the ones in `cookie.feature`: it runs the step in a nested Behat run and checks its failure message, as [Behat harness conventions](../../../CONTRIBUTING.md#behat-harness-conventions) describes.
6. Run `ahoy update-docs`. The trait's page under `docs/content/web-steps/` or `docs/content/drupal-steps/`, `STEPS.md` and `HELPERS.md` change to match the code, and a new trait gets a page of its own.
7. Commit the regenerated files with your change. `ahoy lint-docs` fails a branch whose generated pages are out of date.

A step's text is public API, so renaming a step is a breaking change that waits for the next major release, as [Public API and versioning](../architecture/versioning.md) describes.

## Add or change a helper

A public method of a trait that isn't a step, a hook or a transform is a helper that projects call from their own steps, so it needs a docblock summary and a name with a verb, and it gets the same care as a step. `ahoy update-docs` and `ahoy lint-docs` both stop on a public helper with no summary, unless its docblock marks it `@internal`. A method that only the trait's own machinery needs stays protected. [The helper API](../../../CONTRIBUTING.md#the-helper-api) has the rules, and `ahoy update-docs` publishes the change in `HELPERS.md`, where review sees it.

Helpers are public API too, so renaming or removing one is a breaking change that waits for the next major release.

## Run the tests

Run the suites from the repository root:

```bash
ahoy test-unit                                             # The unit suite
ahoy test-kernel                                           # The kernel suite
ahoy test-coverage                                         # Unit and kernel, with coverage
ahoy test-bdd                                              # Every Behat scenario
ahoy test-bdd tests/behat/features/cookie.feature          # The scenarios of 1 feature file
ahoy test-bdd -- --tags=wip                                # The scenarios tagged @wip
ahoy test-bdd-coverage tests/behat/features/cookie.feature # 1 feature file, with coverage
ahoy cli "php scripts/check-coverage.php CookieTrait"      # A trait's coverage and missed lines
```

A test sits where its subject sits: `src/Steps/Helper/Web/StringTrait.php` is tested by `tests/phpunit/src/Unit/Steps/Helper/Web/StringTraitTest.php`. Codecov's `codecov/patch` and `codecov/project` checks are required, so a code path no test reaches needs a test rather than a coverage marker. CI collects coverage on 2 legs, both on Behat 3: 1 that runs the browser steps through Selenium and 1 through headless Chrome. Locally, `scripts/check-coverage.php` reads the coverage of the last `ahoy test-bdd-coverage` run, nested `@test-trait` runs included. [Running tests](../../../CONTRIBUTING.md#running-tests) and [Coverage markers](../../../CONTRIBUTING.md#coverage-markers) cover the details.

## Run the linters

Run the linters from the repository root:

```bash
ahoy lint       # The code checks
ahoy lint-fix   # Fix what the linters can fix by themselves
ahoy lint-docs  # The step rules and the generated pages
```

`ahoy lint` doesn't run `ahoy lint-docs`, and CI's Lint job runs both, so run both before you push. [Lint](../../../CONTRIBUTING.md#lint) lists what `ahoy lint` runs, and [Settled style questions](../../../CONTRIBUTING.md#settled-style-questions) answers the style questions review would otherwise ask.

## Check the architecture docs

A change to a layer, a backend, a capability, the extension's wiring or the scenario lifecycle changes the [architecture pages](../architecture/README.md) and their diagrams. The `update-architecture-docs` skill redraws them from the code, so ask an AI agent to "update architecture docs" before you open the pull request. Most changes aren't structural, and then the skill leaves everything as it is. Without an agent, edit the pages by hand and redraw a diagram in draw.io, as [Diagram sources](../architecture/README.md#diagram-sources) describes.

## Open a pull request

Send it against `main`, which holds the 4.x line, or against `3.x` for a fix to the 3.x LTS release. CI runs 3 jobs:

- The test matrix runs the unit, kernel and Behat suites across PHP, Drupal and Behat versions.
- Lint checks the code and the generated pages.
- Docs builds and tests this site.

Codecov then reports `codecov/patch` and `codecov/project` from the 2 coverage legs. [Continuous integration](../../../CONTRIBUTING.md#continuous-integration) explains each leg. A change to these docs also follows [Documentation](documentation.md).

Confirm that every check on the pull request passes, `codecov/patch` and `codecov/project` included.
