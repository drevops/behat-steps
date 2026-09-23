# Architecture

This is a walkthrough of how Behat Steps works: what the pieces are, and how a Gherkin step travels from a feature file to a browser or a Drupal API. Each section is traced from the sources it names, and the diagrams sit next to the prose that explains them.

This document and its diagrams are generated and maintained by an AI agent via the `update-architecture-docs` skill in [.claude/skills/update-architecture-docs/SKILL.md](../../.claude/skills/update-architecture-docs/SKILL.md). The content is derived from the source code. If this documentation and the code disagree, the code wins.

Looking for a reference instead? [STEPS.md](../../STEPS.md) lists the steps, [HELPERS.md](../../HELPERS.md) lists the toolbox behind them, and [docs/configuration.md](../configuration.md) lists the options and tags. All three are generated.

## Diagram sources

Every diagram is a PlantUML source in this directory, rendered to a committed light `.svg`. Both are tracked, so a reviewer sees the source diff and the rendered result.

| Source | Renders | Shows |
| --- | --- | --- |
| `architecture.puml` | `architecture.svg` | The 3 library layers, the consuming project, and the runtime around them |
| `class-traits.puml` | `class-traits.svg` | Every step trait in both namespaces, and the context hierarchy they mix into |
| `class-context.puml` | `class-context.svg` | The context hierarchy, the managers, the helper traits, representative step traits, and the exceptions a failing step throws |
| `class-drivers.puml` | `class-drivers.svg` | The Behat-free driver layer: the base contract, the capability interfaces, the 3 drivers, and the Core bridge |
| `dataflow-step.puml` | `dataflow-step.svg` | A step running in a consuming project |
| `dataflow-docs.puml` | `dataflow-docs.svg` | `docs.php` reflecting, validating and rendering every reference document |
| `dataflow-tests.puml` | `dataflow-tests.svg` | The fixture Drupal site, the nested Behat harness, and the coverage merge |

Regenerate every SVG after editing any source:

```bash
plantuml -tsvg docs/architecture/*.puml
```

PlantUML must be on the `PATH`. Install it with `brew install plantuml` on macOS or `apt-get install plantuml` on Debian and Ubuntu.

PlantUML is a Java application and needs a JRE or JDK 11 or later. macOS does not ship one, so check with `java -version` and install a JDK if that command is not found - the Homebrew formula pulls OpenJDK in as a dependency, which covers most setups. Class, component and activity diagrams also need Graphviz, which Homebrew pulls in the same way.

## Three layers

The library is no longer just a bag of traits. It's 3 layers, stacked, and the boundary between them is the most important thing to understand here.

**`src/Driver/` - the driver layer.** Talks to Drupal. Knows nothing about Behat.

**`src/Behat/` - the integration layer.** Wires the driver layer into a Behat suite: the extension, the service container, the managers, the 5 context classes, the entity-creation hooks.

**`src/Helper/` - the shared internals.** 7 step-free traits, each named for one concern, composed by whichever step traits and contexts need them.

**`src/Steps/` - the vocabulary.** The step traits, split into `Steps\Web` and `Steps\Drupal`. This is the layer a consuming project registers, or mixes into a context of its own.

![Component architecture](architecture.svg)

The layering rule is enforced, not just documented. `scripts/lint-layers.php` declares 2 layers and the namespaces each one excludes: `src/Driver` may not reference `Behat` or `Mink`, and `src/Steps/Web` with `WebRawContext` may not reference `Drupal` beyond `Drupal\Component\Utility\Random`. `ahoy lint` runs it alongside PHP_CodeSniffer, PHPStan, Rector and gherkinlint. So the driver layer stays usable without Behat loaded and the web half without Drupal, and the check catches the first import that would break either rather than the tenth.

The dependency footprint reflects the shift. `composer.json` requires PHP 8.3+, Behat 3.33 or 4, Mink and the BrowserKit driver, plus `drupal/core-utility`, `friends-of-behat/mink-extension`, Guzzle, `webflo/drupal-finder`, and 5 Symfony components. This is a framework now, not a trait bag.

## The driver layer

A driver is the thing that actually talks to Drupal. `DriverInterface` is deliberately tiny - `getRandom()`, `bootstrap()`, `isBootstrapped()` - and everything else a driver can do is expressed as a separate capability interface in `Driver\Capability`: content, users, roles, config, modules, cache, cron, batch, language, mail, blocks, watchdog, authentication, creation aliases.

3 drivers implement different slices of that set. `DrupalDriver` bootstraps Drupal in-process and implements all 14. `DrushDriver` shells out and implements the 8 Drush can service. `BlackboxDriver` implements the base contract only, for testing a remote site with no Drupal access at all.

![Class structure: the driver layer](class-drivers.svg)

This is what makes a step's requirements explicit rather than implicit, and it is also how a driver is chosen. A step names the capability it needs - a step that creates a node asks for `ContentCapabilityInterface` - and `DriverManager` walks the scenario's driver order and hands back the first driver implementing it, bootstrapping only that one. When none does, the step fails with `UnsupportedDriverActionException` naming the capability and the order, instead of a fatal error somewhere deeper.

The order itself comes from the suite: its `drivers` setting is both the allow-list and the precedence order, and a `@driver:NAME` tag moves one of those names to the front for a single scenario or feature. A step never names a driver, so the shipped vocabulary carries over unchanged to a driver a project registers itself.

`DrupalDriver` delegates the messy part - turning a Gherkin table into a saved entity - to `Driver\Core`. That's where the field handlers live, one per field type (`DatetimeHandler`, `EntityReferenceHandler`, `ImageHandler`, `LinkHandler`, `AddressHandler` and a couple of dozen more), along with the classifiers that pick a handler and the parser that walks a stub's fields. `Driver\Alias` resolves human-friendly values into real ones: an author name into a uid, a parent term name into a tid, a vocabulary label into a machine name.

## The integration layer

`BehatStepsExtension` is a Behat extension registered under the `behat_steps` config key, and it replaces the Drupal Extension entirely. It loads the service definitions, registers the drivers named in the Behat configuration, validates the `drivers` list against those registrations, wires the managers, and aliases the library's `DocumentElement` over Mink's own.

`DriverListener` builds the driver order once per scenario, before the first step: it takes the configured `drivers` list, moves every `@driver:` name to the front, and hands the result to `DriverManager`. A tag naming a driver the list does not hold fails there, at scenario start, so a typo cannot quietly run the wrong driver.

The library also ships its own `MinkExtension`, registered separately in the Behat configuration. It wraps Mink's extension rather than extending it, because Mink 3 declares that class `final`, and it adds 2 things on top: a `browserkit_http` driver that runs through Drupal's test browser, and a deprecated `ajax_timeout` setting. It passes `registerDriverFactory()` through to the wrapped extension, so an extension such as the Chrome one can still register its driver.

The context layer mirrors the vocabulary split. `RawContext` carries what both halves share and registers no steps:

- Driver access: `driverFor()`, which resolves the capability a step names, and `getDriver()` for the rare caller that wants one suite driver by name.
- Option resolution: `getOption()` reads a trait's option through the declaration default, the extension's `steps` section, the context's `config` argument and the scenario's tags.
- Authentication delegation: `getAuthenticationManager()`, because `BasicAuthTrait` is a web trait and calls it.
- The hook dispatcher and `skipTag()`.

`WebRawContext` adds the web helper traits and nothing else. `DrupalRawContext` adds the Drupal lifecycle, still without registering a step:

- Entity creation (`nodeCreate`, `userCreate`, `termCreate`, `entityCreate`, `languageCreate`), each dispatching before/after hooks so a project can adjust a stub in flight.
- Cleanup: `cleanEntities`, `cleanUsers` and `cleanRoles` run after the scenario and delete what it created, in reverse.
- Authentication: `login`, `logout`, `loggedIn`, delegated to `AuthenticationManager`.

Note where cleanup lives. It is the context's job, not a trait's - which is why a project gets it by extending `DrupalRawContext` rather than by remembering to mix a trait in. A suite that registers only `WebContext` never runs those hooks at all.

`WebContext` and `DrupalContext` sit on top, each composing every trait of its matching `src/Steps/` directory. They are siblings rather than a chain: a Drupal suite registers both, and `ContextCompositionTest` holds the directory-to-context coverage in both directions.

![Class detail: context, managers, helpers and step traits](class-context.svg)

## The step vocabulary

Traits live in 2 places, and the split is meaningful:

- `src/Steps/Web/` in `DrevOps\BehatSteps\Steps\Web` - 28 traits that talk to Mink and know nothing about Drupal. `PathTrait`, `ElementTrait`, `JsonTrait`, `RegionTrait`, `CommandTrait` and friends.
- `src/Steps/Drupal/` in `DrevOps\BehatSteps\Steps\Drupal` - 29 traits that go through the driver. `ContentTrait`, `UserTrait`, `MediaTrait`, `DrushTrait`, `WatchdogTrait`, and so on.

That directory split isn't just tidiness. `docs.php` reads a trait's context straight off its subdirectory under `src/Steps`, so a file's location decides which index it lands in, and it also decides which shipped context has to compose the trait. The driver layer under `src/Driver/` and the helper traits under `src/Helper/` are library code, not vocabulary, and are not scanned.

Each trait carries its steps as PHP attributes - `#[Given]`, `#[When]`, `#[Then]` from `Behat\Step\*` - sitting directly on the method that implements them. There's no `.yml` mapping and no separate registration step. The docblock above the method isn't decoration either: `docs.php` parses it, and the `@code` example inside it is mandatory.

Every trait declares what it needs from its host with `@phpstan-require-extends`: 45 name `RawContext` because they reach for the driver, and 12 name Mink's `RawMinkContext` because a session is all they touch. Mix a trait into a class without that ancestry and PHPStan says so before a test ever runs. `ContextCompositionTest` composes those 12 into a bare `RawMinkContext` subclass and holds the fixture against the annotations, so a requirement that tightens is caught.

![Class structure: step traits](class-traits.svg)

Step traits never `use` other step traits. Shared logic goes in a step-free trait under `src/Helper/` named for its concern - last-step tracking, the request header bag, string shaping, JavaScript support detection, table transposition, fixture-file resolution, direct Drupal queries - and nowhere else. A helper trait composed by a step trait and by the raw context under it holds one property slot, so both reach the same state.

## Flow 1: a step runs

Nothing in this library is invoked directly. Behat owns the loop, and the traits are just where the matching methods happen to live.

When Behat starts, it instantiates every registered context, injects the managers through `DriverAwareInitializer`, and scans each class for step attributes - including every attribute inherited through a `use` statement. Matching a Gherkin line to a method is then ordinary Behat behaviour. The trait method runs with `$this` bound to the context, so `$this->getSession()` reaches Mink and `$this->getDriver()` reaches whichever driver the suite configured.

Because a step attribute is inherited through `use`, two registered contexts composing the same trait register its steps twice and Behat fails with a `RedundantStepException`. That is why `WebContext` and `DrupalContext` compose disjoint sets, and why a project that hand-composes a trait registers its own context instead of the shipped one that already carries it.

![Data flow: a step runs](dataflow-step.svg)

Assertions fail by throwing, and which exception is part of the public contract:

- A trait with a Mink session throws `ExpectationException`, passing the driver as the second argument so the message carries page context. A missing element throws `ElementNotFoundException`.
- A trait with no Mink session - `CommandTrait`, `Drupal\ConfigTrait`, `ModuleTrait`, `StateTrait`, `RedirectTrait` - throws `DrevOps\BehatSteps\Exception\AssertionException`, which needs no driver.
- A bad step argument or unmet prerequisite is not an assertion failure and throws `\RuntimeException`.
- A capability the active driver lacks throws `UnsupportedDriverActionException`.

Every lifecycle hook can be switched off from a feature file. Tag a scenario `@behat-steps-skip:JavascriptTrait` to opt a whole trait out, or `@behat-steps-skip:fileDownloadBeforeScenario` to disable a single hook. Entity cleanup honours the same convention, plus `@behat-steps-entity-cleanup-skip:<entity_type>` for leaving one type in place.

Every one of those tags is read through `Tag`, which strips the leading `@` first. Behat 3 removes it by default and Behat 4 keeps it, so going through `Tag` is what lets the same tag match on both.

## Flow 2: the reference documentation generates itself

Every reference document is generated, and `docs.php` is the only thing that generates them. It's a plain procedural script - top-level functions, no classes - and it runs against the fixture site's autoloader because it needs to reflect over real Drupal-dependent traits.

One run writes 4 targets: `STEPS.md` and the step index in `README.md`, `HELPERS.md`, and the option and tag tables in `docs/configuration.md`. Each lands in a marked block of its file, and only the targets whose block actually changed are written.

The trick is that it doesn't scan the filesystem for step definitions. It reflects over `WebContext` and `DrupalContext`, which between them compose every trait in the library. That makes composition the source of truth, and it documents exactly what a project gets by registering them: a trait file that exists but was never added to its context throws rather than being quietly skipped.

The same reflection pass yields both halves of the package, and visibility is what separates them. A public method carrying a `Behat\Step\*` attribute is vocabulary and goes to `STEPS.md`; a public method carrying no Behat attribute at all is toolbox and goes to `HELPERS.md`, unless its docblock withdraws it with `@internal`. A protected method is an implementation detail and appears in neither. On a trait both halves are filtered by the trait-name prefix every member already carries, so a method borrowed from elsewhere is not published under a trait that merely composes it. `TOOLBOX_CLASSES` adds the 3 raw contexts to the toolbox half, because a project's own step definitions are written against their lifecycle methods as much as against a trait's helpers; a class is read without that prefix filter, since its methods carry no trait name.

![Data flow: reference documentation generation](dataflow-docs.svg)

The option and tag tables come from the code rather than from the reflection pass: the options are read off the config tree `BehatStepsExtension::configure()` builds, and the tags off `tag_registry()`. Neither can be added without appearing in the reference.

The validation half matters more than the rendering half. It's where the project's conventions stop being a style guide and start being enforced: a `@When` step without `I `, a `@Then` step whose method name lacks `Assert`, a method with 2 step attributes, a step with no `@code` example, a published helper with no summary, a `getenv()` name that `docs/configuration.md` never mentions - each is a hard error. `tag_registry()` does the same job for tags, guarding against separator drift so that `@module:views` never quietly becomes `@module-views`.

Run with `--fail-on-change` (that's `ahoy lint-docs`), the script regenerates the blocks in memory and exits non-zero if they don't match what's committed, naming the targets that drifted and writing nothing. So the documentation can't drift, because a drifted build is a red build.

## Flow 3: how the library tests itself

This is the interesting part, and it's genuinely a bit unusual. A library of Drupal test steps can't be tested without a Drupal site, so the repository builds one - and then, for the failure paths, runs Behat inside Behat.

### Building the fixture site

`scripts/provision.sh` creates a throwaway Drupal site under `build/`. The `DRUPAL_VERSION` variable picks the core major, `11` unless set, and names the fixture directory it copies from - `tests/behat/fixtures_drupal/d11/` or `d12/`. It then merges the library's own Composer requirements into that fixture's `composer.json` - including every package named in `suggest`, because the fixture site has to exercise all the traits at once - dropping any package the fixture already pins, so the fixture's constraint is the one that survives. It installs Drupal with `drush si standard`, appends a couple of `$config` overrides to `settings.php` so `ConfigOverrideTrait` has something real to read, copies `tests/behat/fixtures/` into the site's files directory, and confirms the site bootstraps before handing back.

Between those last 2 it checks that the configuration import landed, by reading a config entity only the fixture defines. `drush cim` can enable the modules, abort on a fatal while it is creating config entities, and still exit 0, which leaves a site that boots and has none of the content types the suite asserts on.

The `BEHAT` variable picks the Behat major, `3` unless set. `composer.json` allows both Behat 3.33 and Behat 4, and `composer update --with="behat/behat:^${BEHAT}"` narrows the fixture to one. Each major combination then drops what it cannot install: a Behat 4 build removes `dmore/behat-chrome-extension`, and on Drupal 11 also `dvdoug/behat-code-coverage`.

Drupal 12 needs contrib relaxed at two layers, because no contrib release declares it yet. For the Composer solve, `d12/composer.json` lists every contrib module under `extra.drupal-lenient.allowed-list` for `mglaman/composer-drupal-lenient` to strip the core constraint from; a plugin only shapes a solve it is already installed for, so provisioning installs it globally first - Composer loads global plugins for local projects. For Drupal itself, which reads `core_version_requirement` from each extension and refuses to enable one that excludes the running major, provisioning appends `|| ^12` to that key across the contrib extensions in `build/`, leaving the fixture sources untouched.

Behat then runs from inside `build/` but with the project-root `behat.php`, which is why several paths in the config look one level off.

### The suite

`behat.php` wires up `FeatureContext` (`WebContext` plus the web test-only steps and overrides), `DrupalFeatureContext` (`DrupalContext` plus the Drupal ones), `BehatCliContext` (the nested runner), Mink's own `MinkContext`, the screenshot extension, and a PHP built-in server that serves `tests/behat/fixtures/` on port 8888 for the traits that need a static file and no Drupal at all. The `BehatStepsExtension` settings choose the `drupal` API driver, the Drush root and global options, the message selectors, the named regions, and the path mappings. The coverage extension is registered only when it is installed, so a Behat 4 build runs without it. Behat 4 reads only PHP configuration, and Behat 3.33 reads the same file.

Default sessions run through BrowserKit. `@javascript` scenarios run through Selenium2, or through headless Chrome over the DevTools Protocol if you use the `chrome_headless` profile - which inherits everything and swaps only the JavaScript session, so the same suite proves the steps are driver-portable.

### Behat inside Behat

Asserting that a step *fails correctly* is awkward from inside the same run: the failure would fail your own scenario. So scenarios tagged `@trait:SomeTrait` take a detour.

`BehatCliTrait::behatCliBeforeScenario` reads the trait names out of the tag, writes a minimal `DrupalRawContext` subclass composing just those traits into a temporary directory, and `BehatCliContext` runs a real `behat` subprocess against it. The generated context extends the Drupal base because a tag names a trait from either half, and that base carries both halves' plumbing. The outer scenario then asserts on the subprocess's exit code and output. The subprocess reads a `behat.php` that `BehatCliTrait` writes, and the generated context declares its steps and hooks as PHP attributes, because Behat 4 ignores docblock annotations. One quirk worth knowing: nested PyStrings are written with `'''` and converted to `"""` on the way out, because you can't nest `"""` inside `"""` in Gherkin.

![Data flow: the fixture site and the nested Behat harness](dataflow-tests.svg)

### Coverage, and the file everyone reads wrong

Because half the failure paths are only ever exercised inside subprocesses, coverage arrives in 2 pieces and has to be stitched together.

The outer run writes `.logs/coverage/behat/`. Each nested subprocess drops its own file into `.logs/coverage/behat_cli/phpcov/`. `scripts/merge-coverage.php` folds them together and writes the merged report to `.logs/coverage/behat_cli/cobertura.xml`.

That merged file is the real number. The `behat/` one only ever shows the direct scenarios, so it reads lower - which is exactly the kind of thing that sends someone off chasing coverage that already exists. `scripts/check-coverage.php` defaults to the merged file for that reason.

Alongside all this, `tests/phpunit/` holds ordinary unit tests for the parts that don't need a browser: `docs.php` itself, the driver layer, the helper traits, and the convention tests at the root of `tests/phpunit/src/` that hold the naming, member order, public surface, layer and context-composition rules.

## Continuous integration

`.github/workflows/test.yml` has 2 jobs, both running inside the project's own Docker Compose stack so CI and local development execute the same commands.

`lint` runs on PHP 8.4. It provisions the fixture site, checks that `composer.json` is normalized, and runs `ahoy lint` - `composer validate`, `parallel-lint`, PHP_CodeSniffer, PHPStan, Rector in dry-run mode, gherkinlint over the feature files, and `scripts/lint-layers.php` for the driver-layer and web-half boundaries - followed by `ahoy lint-docs`, which is the `docs.php --fail-on-change` gate.

The `test` matrix is PHP 8.3, 8.4 and 8.5 against Drupal 11, each run twice - once with `normal` dependency resolution and once with `lowest` - on Behat 3, and the same 6 combinations again on Behat 4, which the `behat` matrix key passes to `scripts/provision.sh` as `BEHAT`. 2 more legs run the Behat 3 suite through the `chrome_headless` profile, on PHP 8.3 and 8.5 with `normal` dependencies. Behat 4 has no Chrome leg, because the Chrome extension has no release that accepts Behat 4.

2 further legs run Drupal 12, which the `drupal_version` matrix key passes as `DRUPAL_VERSION`, on PHP 8.5 and Behat 4 with `normal` and `lowest` dependencies - the whole grid that major allows, since core 12 requires PHP 8.5 and Symfony 8 while the newest Behat 3 caps Symfony at 7. Every leg names the majors it runs, as in `Test PHP 8.5, Drupal 12, Behat 4, Deps normal`, and the branch ruleset requires those names.

The unit and kernel suites run on every leg without a profile, since a profile changes how the Behat suite runs and not what PHPUnit covers. Coverage is collected on the 2 legs flagged `coverage` - PHP 8.3 / normal on Behat 3, through Selenium2 and through headless Chrome - and Codecov merges the uploads into one report. Test artifacts - logs, screenshots, coverage - come back from every leg, passing or failing.

## Regenerating this document

After a structural change - a new layer or namespace, a change to how a context composes the lifecycle, a new driver or capability, a change to how `docs.php` discovers steps, a change to the fixture harness or the CI matrix - ask the AI agent to "update architecture docs". It re-traces the affected diagrams and prose from the current code via the `update-architecture-docs` skill, and re-renders the SVGs in the same pass.
