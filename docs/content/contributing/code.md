---
title: Code
sidebar_label: Code
sidebar_position: 1
description: Set up the fixture site, find where a change goes, add a step or a helper, run the tests and the linters, and open a pull request, with the rules each part follows.
---

# Contributing code

This page walks through a code change from start to finish: setting up, finding where the change goes, making it, checking it and sending it. [CONTRIBUTING.md](../../../CONTRIBUTING.md) holds the rules themselves, and each part below links the ones it touches.

## Set up

You need [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy) and [Ahoy](https://github.com/ahoy-cli/ahoy), and no other web server running on your machine. Check the repository out in a directory Docker can share, then run:

```bash
pygmy up
ahoy build
```

`ahoy build` installs the dependencies and builds a fixture Drupal site in `build/`, which the unit, kernel and Behat suites all run against. `ahoy info` prints its address, and `ahoy --help` lists every command. [Local environment setup](../../../CONTRIBUTING.md#local-environment-setup) has the details.

## Find where the change goes

- A step that works on any site goes in a trait under `src/Steps/Web`, and a step that only means something on a Drupal site in 1 under `src/Steps/Drupal`.
- Logic that several traits share goes in a helper trait under `src/Steps/Helper`.
- Anything a step needs from the site goes on a capability in the backend layer, `src/Backend`, never in the step itself.
- How the library plugs into Behat and Mink lives in the extension layer, `src/Extension`.

The [Architecture](../architecture/README.md) pages explain each layer, and [Layers](../../../CONTRIBUTING.md#layers) lists what each one may reach. [Anatomy of a trait](../architecture/anatomy-of-a-trait.md) shows the shape every trait follows.

## Add or change a step

1. Word the step by [the steps format](../../../CONTRIBUTING.md#steps-format): who acts, `Given` for a state, `When` for an action, `Then` with `should`, and where each value goes. `ahoy lint-docs` checks the rules it marks "(lint)".
2. Name its method by [the method naming conventions](../../../CONTRIBUTING.md#method-naming-conventions), and keep its body [thin](../../../CONTRIBUTING.md#thin-step-bodies): it parses its arguments, calls helpers and guards on what they return.
3. Give it a docblock with an example between `@code` and `@endcode`, and an `@actor:person` or `@actor:system` line. The trait's page and `STEPS.md` are generated from both.
4. Add a scenario that runs the step to the trait's feature file in `tests/behat/features`. A unit test fails any step that no scenario runs, as [Behat tests](../../../CONTRIBUTING.md#behat-tests) explains.
5. Run `ahoy update-docs` to regenerate the step and helper pages, `STEPS.md` and `HELPERS.md`.

A step's text is public API, so renaming 1 is a breaking change that waits for the next major release, as [Public API and versioning](../architecture/versioning.md) describes.

## Add or change a helper

A public method of a trait is a helper that projects call from their own steps, so it needs a docblock summary and a name with a verb, and it gets the same care as a step. A method that only the trait's own machinery needs stays protected. [The helper API](../../../CONTRIBUTING.md#the-helper-api) has the rules, and `ahoy update-docs` publishes the change in `HELPERS.md`, where review sees it.

## Run the tests

```bash
ahoy test-unit                # The unit suite
ahoy test-kernel              # The kernel suite
ahoy test-bdd                 # Every Behat scenario
ahoy test-bdd path/to/file    # The scenarios of 1 feature file
ahoy test-bdd -- --tags=wip   # The scenarios tagged @wip
```

A test sits where its subject sits: `src/Steps/Helper/Web/StringTrait.php` is tested by `tests/phpunit/src/Unit/Steps/Helper/Web/StringTraitTest.php`. Coverage is a required check, so a branch no test reaches needs a test rather than a coverage marker. [Running tests](../../../CONTRIBUTING.md#running-tests) and [Coverage markers](../../../CONTRIBUTING.md#coverage-markers) cover the details.

## Run the linters

```bash
ahoy lint       # Every check the Lint job runs
ahoy lint-fix   # Fix what the linters can fix by themselves
ahoy lint-docs  # Fail when a generated page is out of date
```

[Lint](../../../CONTRIBUTING.md#lint) lists what `ahoy lint` runs, and [Settled style questions](../../../CONTRIBUTING.md#settled-style-questions) answers the style questions review would otherwise ask.

## Check the architecture docs

A change to a layer, a backend, a capability, the extension's wiring or the scenario lifecycle changes the [architecture pages](../architecture/README.md) and their diagrams. The `update-architecture-docs` skill redraws them from the code, so ask an AI agent to "update architecture docs" before you open the pull request. Most changes aren't structural, and then the skill leaves everything as it is.

## Open a pull request

Send it against `main`, or against `3.x` for a fix to the 3.x LTS release. CI runs 3 jobs: the test matrix runs the unit, kernel and Behat suites across PHP, Drupal and Behat versions, Lint checks the code and the generated pages, and Docs builds and tests this site. [Continuous integration](../../../CONTRIBUTING.md#continuous-integration) explains each leg.

A change to these docs also follows [Documentation](documentation.md).
