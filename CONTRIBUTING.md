# Contributing

Below are some guidelines for developing and maintaining the Behat steps.

New here? [docs/architecture/](docs/architecture/README.md) walks through how the pieces fit together - the trait library, the generated step documentation, and the fixture site the tests run against - with UML component, class and sequence diagrams.

## Steps format

A consistent steps format is essential for the readability and maintainability
of tests. Follow these guidelines:

- **General Guidelines**:
  - Avoid using regular expressions to define a step definition. Use tuple
    format instead for better clarity and maintainability.
  - Use descriptive placeholder names to help users quickly understand the
    expected value: `content_type` instead of `type`.
  - Use `the following` for tabled content.
  - For anything identified by a property, use `with`: <code>Then the link :
    link <b>with</b> the title :title should exist</code>
  - Avoid optional words like `(the|a)`. Provide a single form instead to ensure
    consistency. The `(s)` plural token is the exception: a step whose noun
    agrees with a count or a list keeps it, as in `:count row(s)` and
    `the role(s) :roles`, so both forms read naturally.
  - Omit unnecessary suffixes like `on the page` since it is implied.
  - All method names should begin with the trait name: `userAssertHasRoles()` for `UserTrait`. The prefix is the trait name minus its `Trait` suffix with the first letter lowercased, and the character after it is uppercase: `menuLoadByLabel()`, not `loadMenuByLabel()`. The prefix is not also the verb: `waitSeconds()`, not `waitWaitForSeconds()`. It applies to every member a trait mixes into the context - steps, helpers, properties and constants - since any of them can collide with another trait's. `tests/phpunit/src/TraitMethodNamingTest.php` enforces it.

- **`Given`**:
  - Defines test prerequisites—conditions or data that must exist before the
    test runs.
  - Use words like `exists` or `have`.
  - Avoid using `should` or `should not` (these are reserved for assertions).
  - Never refer to the person: no `I`, `my`, `me`, `we`, `us` or `our` anywhere in the step. A precondition is a fact about the world, not something the person does.

- **`When`**:
  - Describes an action and must contain an action verb.
  - Use the format `When I <verb>`. The step must start with `I` followed by a space - the first person is what separates an action from a precondition.

- **`Then`**:
  - Specifies assertions and expectations.
  - Use `should` and `should not` to clearly indicate assertions.
  - Start the step with the entity being asserted, e.g.,
    `Then the link with a title :title exists`.
  - Never refer to the person: no `I`, `my`, `me`, `we`, `us` or `our` anywhere in the step. Start with the entity being asserted.
  - Methods should include the `Assert` prefix, e.g., `userAssertHasRoles()`.

We have some automated check for the steps format.
Run `ahoy lint-docs` to validate the format of the steps.

## Method naming conventions

Every method a trait contributes begins with the trait's own name, so that traits mixed into one context cannot collide. `tests/phpunit/src/TraitMethodNamingTest.php` enforces this and the three conventions below.

### Assertions

An assertion method reads `<trait>Assert<Subject><Predicate>`.

- **Existence**:
  - Singular subjects → `Exists` or `NotExists` (e.g., `fieldAssertExists()`, `taxonomyAssertVocabularyNotExists()`)
  - Plural subjects → `Exist` or `NotExist` (e.g., `redirectAssertExist()`)
- **Containment**: always `Contains` or `NotContains` (e.g., `xmlAssertElementContains()`, `responseAssertHeaderNotContains()`)
- **Subject first**: the thing being asserted about precedes what is asserted of it, as in `responseAssertHeaderExists()` rather than `responseAssertContainsHeader()`.
- **No copula**: `Assert` already states that the subject is something, so `Is` is dropped - `elementAssertVisible()`, not `elementAssertIsVisible()`.

### Negation

`Not` is the only negation particle, and it sits immediately after `Assert<Subject>`, directly before the predicate it negates. A negative name is its positive counterpart with `Not` inserted and nothing else changed.

| Instead of | Write |
| --- | --- |
| `userAssertHasNoRoles()` | `userAssertNotHasRoles()` |
| `emailAssertNoMessagesSent()` | `emailAssertMessagesNotSent()` |
| `userAssertIsNotBlocked()` | `userAssertNotBlocked()` |
| `elementAssertIsVisuallyHidden()` | `elementAssertNotVisuallyVisible()` |

The determiner `No`, the copula `Is`, an antonym standing in for a negation, and `DoesNot` or `DoNot` are all out.

### Consumer override points

A documented override point that supplies a value is `<trait>Get<Noun>()`, booleans included - `modalGetWaitTimeout()`, `commandGetTimeout()`, `accessibilityGetFailOnIncomplete()`, `diagnosticsGetShowUrl()`. A method that computes rather than supplies keeps a verb describing what it does, as in `accessibilityResolveTags()` or `contentResolveNidByTitle()`.

### Spelling

`Normalize`, not `Normalise`, in method names and in prose.

## The helper API

The package is 2 products in 1: the vocabulary (the steps) and the toolbox (the helpers the steps are built on). A project that outgrows the raw vocabulary stops calling the toolbox from Gherkin and starts calling it from PHP, so the helpers are public API in the same sense the step text is. [docs/scenario-styles.md](docs/scenario-styles.md) argues why.

**Visibility is the marker.** A `public` method is the toolbox; a `protected` one is an implementation detail that promises nothing and may change in any release. Nothing else distinguishes the two, which is why a helper worth calling from a project's own step definitions is declared `public` and one that only serves the machinery stays `protected`.

A member is published in [HELPERS.md](HELPERS.md) when all of the following hold. Everything published is covered by semantic versioning.

- It is declared by a trait under `src/Steps` or by a class in `docs.php`'s `TOOLBOX_CLASSES`.
- It is `public`. A `private` member cannot be reached from a composing context and has no place in a trait.
- It begins with its trait's name, which is the collision rule every trait member follows anyway.
- It carries no `#[Given]`, `#[When]`, `#[Then]`, `#[Transform]` or hook attribute. Those are registered with Behat and belong to the vocabulary.
- Its docblock carries no `@internal`.

Three obligations follow from publishing a member:

- **It needs a summary.** `ahoy lint-docs` fails on a published helper with no docblock description, because the reference would carry a blank entry, and `PublicSurfaceTest` fails on the same thing from the other side. A `{@inheritdoc}` docblock is resolved to the interface or parent that declares the method, so implementing an interface is enough.
- **It is named as carefully as a step.** The naming conventions above apply to a helper exactly as they do to a step method.
- **It shows up in review.** [HELPERS.md](HELPERS.md) is committed and gated by `--fail-on-change`, so promoting a method to `public` lands in the diff with its signature and summary. That diff is what keeps the surface deliberate: a method cannot be narrowed again before the next major.

Withdraw a member that exists only to serve the machinery with `@internal`, naming who calls it:

```php
/**
 * Sets the driver manager.
 *
 * @internal
 *   Injection point called by the context initializer.
 */
```

A step body should be a thin wrapper over a named helper, so that every behavior a scenario can reach is also reachable from a project's own step definitions. Write new steps that way, and extract a helper when you touch a step that keeps its logic inline.

## Member ordering within a trait

Traits lay their members out in this order:

1. Trait composition (`use`), then constants, then properties.
2. Hooks (`#[BeforeScenario]`, `#[AfterStep]` and the like).
3. `Given` steps, then `When` steps, then `Then` steps.
4. Helpers, public and protected together. Visibility marks what is published, not where a member sits, so a helper stays next to the ones it reads with.

Within each of those groups, keep the members in whatever order reads best - the rule settles the groups, not what happens inside one. `tests/phpunit/src/MemberOrderTest.php` enforces it.

Reordering an existing trait into this layout leaves [STEPS.md](STEPS.md) untouched. `docs.php` already groups steps by `Given`, `When` and `Then` and keeps source order inside each group, so this layout only applies a sort the generated documentation applies anyway.

## Unsettled style questions

Four style questions have no dominant form in this codebase. Both sides of each are correct and behavior-identical where they appear, and converging any of them would churn 25 to 75 sites for no functional gain. Match the surrounding file and do not convert existing code from one form to the other as a drive-by change.

- **Nullable-object absence**: `if (!$element)` (~40 sites) and `=== NULL` (~35 sites) are both accepted. `is_null()` is not - it has been converged away.
- **Array emptiness**: `empty($array)` (~30 sites) and `$array === []` (~25 sites) are both accepted. Newer code leans strict, which is a weak preference rather than a rule.
- **`self::` versus `static::`**: not purely cosmetic. `static::` makes a static member overridable by a composing class, so it widens the public contract. `AccessibilityTrait` uses `static::` deliberately for that reason. Choose based on whether the member is intended as an extension seam, not on local consistency.
- **Docblock tag order and `@code` indentation**: `@param` before `@code` and the reverse both appear, as do flush and indented example bodies. `docs.php` renders `@code` bodies into [STEPS.md](STEPS.md), so changing indentation reflows the generated documentation.

Calling an instance method through `self::` or `static::` is not in this list - that was unambiguous and has been converged to `$this->`.

## Layers

The package ships 3 layers, and the dependency only runs one way: `Steps` on `Behat` on `Driver`.

- **`src/Driver`** is the part that talks to Drupal: it bootstraps a site in-process or shells out to Drush, creates entities, and expands field values into their storage shape. It knows nothing about Behat or Mink, which is what keeps it usable outside a Behat run.
- **`src/Behat`** is the integration: `ServiceContainer/BehatStepsExtension` reads the `behat_steps` configuration and builds the container, `Manager/` holds the driver, authentication, user and mail managers, `Context/RawContext` is the base context a consuming `FeatureContext` extends, and `Hook/`, `Listener/`, `Selector/` and `Generator/` carry the entity-creation hooks, the per-scenario driver selection, the `region` Mink selector and the starter-class generator. `RawContext` registers no step definitions - it owns the scenario lifecycle only.
- **`src/Steps`** is the step vocabulary - traits a consuming `FeatureContext` mixes in. `Generic/` holds the framework-agnostic ones, `Drupal/` the ones that need a Drupal site, and the directory a trait sits in is the context [STEPS.md](STEPS.md) groups it under.

A trait names the context class it needs with `@phpstan-require-extends`, and never composes another step trait: shared logic goes in the step-free `HelperTrait` of its context.

## What a trait needs from the driver

A step is only as portable as the driver behind it, so each trait falls into one of four bands. Which band a trait is in decides whether a scenario has to be tagged `@api`.

- **Nothing.** Every trait under `src/Steps/Generic` except `MessageTrait`, `RegionTrait`, `MappingTrait` and `BasicAuthTrait` reads and drives the page through Mink alone. They run on any driver, against any site, with no Drupal at all.
- **Extension configuration, but no driver.** `MessageTrait`, `RegionTrait` and `MappingTrait` read the `selectors`, `regions` and `mappings` maps that `BehatStepsExtension` injects, and `BasicAuthTrait` reads the authentication manager. They need the extension registered, not a bootstrapped site.
- **A capability interface.** `CacheTrait`'s clear and cron steps, `DrushTrait` and the user and content creation steps ask the active driver for a named capability (`CacheCapabilityInterface`, `CronCapabilityInterface`, `UserCapabilityInterface`, `ContentCapabilityInterface`, `RoleCapabilityInterface`). They work on any driver that implements it, which for most is the Drush driver as well as the in-process one, and they throw naming the missing capability when it does not.
- **The in-process driver.** Every other trait under `src/Steps/Drupal` reaches Drupal's API directly. Each such method calls `RawContext::assertDrupal()` first, which asserts the scenario carries `@api` and that the driver it selected bootstraps Drupal in-process, bootstraps it, and returns it. A scenario missing `@api` gets a `BootstrapException` naming the tag; an `@api` scenario whose configured `api_driver` cannot bootstrap in-process gets one naming the driver.

A new step that touches `\Drupal::` calls `$this->assertDrupal();` as its first statement. That is the only sanctioned bootstrap: nothing else may assume the container exists.

[scripts/lint-layers.php](scripts/lint-layers.php) holds the lower boundary. It reads every file under `src/Driver` and fails on any code reference into the `Behat` or `Mink` namespaces: imports, type declarations, and class names reached through a string. A prose mention in a comment is fine - it's the code references that matter. `ahoy lint` runs it.

## Behat 4 readiness

`composer.json` declares `behat/behat: ^3.33.0 || ^4.0@alpha` and `friends-of-behat/mink-extension: ^2.7.5 || ^3.0@alpha`, so a consumer can install this library on either Behat major. `prefer-stable` keeps a default install on the stable pair; Behat 4 arrives only when a project asks for it.

`src/Behat` plugs into 5 Behat extension points, and each one is written to satisfy Behat 3.33 and Behat 4 at the same time. Keep it that way when touching them.

- **Signatures are typed for Behat 4, widened for Behat 3.** Behat 4 types its interfaces where 3.33 leaves them untyped, so implementations declare the Behat 4 return type (`ClassGenerator::supportsSuiteAndClass(): bool`, `HookScope::getName(): string`, `FilterableHook::filterMatches(): bool`, `Extension::getConfigKey(): string`) and keep the parameter untyped or `mixed` so the 3.33 interface is not narrowed.
- **`MinkExtension` wraps Mink's extension instead of extending it.** Mink declares its own `MinkExtension` `final` from version 3, the release that carries Behat 4 support, so a subclass cannot even load there. The first-party extension implements `Extension` itself and delegates the 5 interface methods and `registerDriverFactory()` to a wrapped instance, so the `browserkit_http` factory swap and the driver factories other extensions register work on both.
- **`DriverListener` reads the event, not the removed interface.** Behat 4 drops `ScenarioLikeTested`. Both `ScenarioTested::BEFORE` and `ExampleTested::BEFORE` carry a `BeforeScenarioTested`, which declares `getFeature()` and `getScenario()` itself in both versions, so the listener type-hints that class.
- **`HookAttributeReader` builds its callable through Behat's factory when there is one.** Behat 4 types the callee constructor as `callable`, and `[class-string, method]` is not callable for an instance method. `ContextMethodCallableFactory` wraps such methods on Behat 4 and is absent on Behat 3, so `makeCallable()` uses it only when the class exists.
- **The `context.class_generator.simple` override survives by service id.** Behat collects generators by tag before an activated extension's `process()` runs and injects them as references, so replacing the definition behind that id swaps the class in both versions.

The test suite follows the same rule. Behat 4 reads only PHP configuration and ignores docblock annotations, so the suite runs from [behat.php](behat.php), `BehatCliTrait` writes a `behat.php` for every nested run, and every step and hook - in `src/` and in `tests/behat/bootstrap/` - is declared with a PHP attribute. Behat 3.33 reads both the same way. Both configurations list every Mink session under `sessions` instead of using the driver-name shorthand, because Mink 3.0.0-ALPHA.1 reads the shorthand with an `Undefined array key "sessions"` warning.

[behat.dist.php](behat.dist.php) is the reference a consumer copies from, so it sets every option `BehatStepsExtension` accepts. `BehatDistConfigTest` names any option missing from it, which is what keeps it complete as the extension grows. Behat never loads it here, because `behat.php` takes precedence.

## Reading tags

Behat 3 strips the `@` from a tag by default and Behat 4 keeps it, while `TaggedNodeInterface::hasTag()` compares strictly, so a bare-name comparison that matches on one major silently fails on the other.

Read tags through [`Tag`](src/Behat/Tag.php), never through `hasTag()` or `getTags()` directly:

```php
// Every tag on the scenario and on the feature that holds it, without the '@'.
$tags = Tag::all($scope);

// Every tag on one node.
$tags = Tag::on($scope->getScenario());

// One tag on one node.
if (Tag::has($scope->getScenario(), 'email')) {
  // ...
}
```

`Tag::normalize()` takes a raw list when none of those fit. Nothing outside `Tag` calls `getTags()` or `hasTag()`, so `grep` finds any new one.

## Dependency policy

Keep the `require` section of `composer.json` minimal - it should contain only what **every** consumer needs regardless of which traits they use.

- **`require`**: the framework and browser abstraction that virtually all steps build on - `php`, `behat/behat`, `behat/mink` - plus what the driver and Behat layers need at runtime. Both ship in `src/`, so every consumer loads them: `drupal/core-utility`, `symfony/process` for the driver, and `friends-of-behat/mink-extension`, `symfony/config`, `symfony/dependency-injection`, `symfony/event-dispatcher` for the extension, its config schema and `RawContext`'s Mink ancestor.
- **`require-dev` + `suggest`**: any package used by only a subset of traits. List it in `require-dev` so this library's own test suite still exercises it, **and** in `suggest` with a message naming the exact trait(s) or step(s) that need it (as `justinrainbow/json-schema` does for `JsonTrait`).

When a new trait needs a package, decide up front: trait-specific packages go in `require-dev` + `suggest`, never in `require`. Demoting a package from `require` to `suggest` later is a breaking change for consumers relying on transitive installation, so batch such demotions into the next major release and document them in [MIGRATION.md](MIGRATION.md).

## Local environment setup

Install [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy), [Ahoy](https://github.com/ahoy-cli/ahoy)
and shut down local web services (Apache/Nginx, MAMP etc)

- Checkout project repository in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control).
- `pygmy up`
- `ahoy build`
- Access built site at http://behat-steps.docker.amazee.io/

Use `ahoy --help` to see the list of available commands.

## Running tests

There are 3 types of tests in this repository: unit tests, kernel tests and Behat tests.

### Unit and kernel tests

Both suites are declared in [phpunit.xml](phpunit.xml) and run against the
fixture site, because the driver layer and the tests around it resolve Drupal
classes from there. Run `ahoy build` first.

Tests live under `tests/phpunit/src/` in a directory named after their suite:
`Unit/` and `Kernel/`. Anything outside `Kernel/` belongs to the unit suite.
Inside a suite directory the path mirrors `src/`, so
`src/Steps/Drupal/HelperTrait.php` is tested by
`tests/phpunit/src/Unit/Steps/Drupal/HelperTraitTest.php`. Tests with no
counterpart in `src/` - the docs generator, the layer linter and the
convention tests - sit at the root of `tests/phpunit/src/`.

```bash
ahoy test-unit      # Run the unit suite

ahoy test-kernel    # Run the kernel suite

ahoy test-coverage  # Run both suites and write one coverage report
```

Coverage is collected across both suites in a single run, so the report covers
everything the tests reach. Its output paths are declared in
[phpunit.xml](phpunit.xml).

### Behat tests

Behat tests are used as functional/integration tests to validate the
functionality of the traits. These Behat tests run in the same way they
would be run in your project: traits are included
into [FeatureContext.php](tests/behat/bootstrap/FeatureContext.php)
and then ran on the
pre-configured [fixture Drupal site](tests/behat/fixtures_drupal/d11)
using [test features](tests/behat/features).

Run `ahoy build` to setup a fixture Drupal site in the `build` directory.

```bash
ahoy test-bdd                # Run all Behat tests

ahoy test-bdd path/to/file   # Run all Behat scenarios in specific feature file

ahoy test-bdd -- --tags=wip  # Run all Behat scenarios tagged with `@wip` tag
```

### Static fixtures

Static fixture files - HTML pages, XML, JSON, images, archives - live in [tests/behat/fixtures](tests/behat/fixtures).

Traits with no Drupal dependency are tested against those files served by a PHP built-in server instead of the fixture Drupal site. Tag the scenario `@phpserver` and address the file directly:

```gherkin
@phpserver
Scenario: Assert that an element exists
  When I visit "http://cli:8888/elements.html"
  Then the element "#top" should exist
```

The server runs for the duration of a tagged scenario and serves `tests/behat/fixtures` at its root, so an edited fixture applies on the next run.

Drupal traits are tested through the fixture site, which receives a copy of the same directory in `build/web/sites/default/files` during provisioning. Run `ahoy copy-files` after editing a fixture that such a scenario reaches through a Drupal path.

### Coverage markers

`@codeCoverageIgnoreStart` and `@codeCoverageIgnoreEnd` suppress **genuinely unreachable** defensive code. They are not a way to hide an untested branch.

The distinction matters because PHPUnit drops ignored lines from the report entirely. Ignoring a line that a test would have covered lowers the reported rate, which is merely wasteful. Ignoring a line that no test covers *raises* it, which reports progress that does not exist. `codecov/patch` and `codecov/project` are required checks, so that second case shifts the baseline every later pull request is measured against.

Mark a block only when a test cannot reach it in this environment:

- An I/O failure that cannot be provoked - `file_get_contents()` returning `FALSE` on a file just written, `tempnam()` failing.
- A type guard that the preceding call makes impossible - `!$node instanceof NodeInterface` directly after a successful load.
- A capability branch for a driver the suite does not run.

Do not mark a branch that a scenario could reach. In particular:

- **Skip-tag guards** (`@behat-steps-skip:<method>`) are reachable by definition - add a scenario carrying the tag.
- **Argument validation** driven by a step parameter is reachable by passing an invalid value.

If a reachable branch has no test, the fix is the test, not the marker.

### Debugging tests

- `ahoy debug`
- Set breakpoint
- Run tests with `ahoy test-bdd` - your IDE will pickup an incoming debug
  connection

## Continuous integration

[.github/workflows/test.yml](.github/workflows/test.yml) runs 2 jobs, and between them they cover all 3 test surfaces in this repository.

### Lint

1 job, on PHP 8.4. It checks that `composer.json` is normalized, then `ahoy lint` runs `composer validate`, `parallel-lint`, `phpcs`, `phpstan`, `rector --dry-run`, `gherkinlint` and [scripts/lint-layers.php](scripts/lint-layers.php), and `ahoy lint-docs` checks [STEPS.md](STEPS.md) for drift. Both are the commands you run locally, and the job is green only when both are.

### Test matrix

| Legs | What they prove |
|---|---|
| PHP 8.3 / 8.4 / 8.5 x Drupal 11 x `normal` / `lowest` x Behat 3 | The library works across the supported PHP range against both the newest and the oldest resolvable dependencies. The `lowest` legs are what hold the Behat 3.33 floor. |
| 2 x `chrome_headless` | The steps drive a browser without Selenium, over the Chrome DevTools Protocol. That driver is Drupal-version independent, so the 2 legs take their breadth from the PHP axis. Both stay on `normal` deps: `dmore/behat-chrome-extension` hands the driver `domWaitTimeout` and `socketTimeout`, which the oldest `dmore/chrome-mink-driver` it accepts does not define, so a `lowest` resolution cannot boot Chrome at all. |
| PHP 8.3 / 8.4 / 8.5 x Drupal 11 x `normal` / `lowest` x Behat 4 | The same unit, kernel and Behat suites pass on Behat 4. See [Behat 4 legs](#behat-4-legs). |
| PHP 8.5 x Drupal 12 x `normal` / `lowest` x Behat 4 | The next core major, on a patched contrib set. See [Drupal versions](#drupal-versions). |

The unit and kernel suites run on every leg that is not driven by a Behat profile, since a profile changes how the Behat suite runs and not what PHPUnit covers.

Coverage is produced on 1 Selenium leg and 1 `chrome_headless` leg, both on Behat 3, merged by Codecov into a single report, and its upload fails the leg rather than passing quietly. Test artifacts (`.logs`) are uploaded from every leg.

### Behat 4 legs

Each Behat 4 leg provisions the fixture with `BEHAT=4`. [scripts/provision.sh](scripts/provision.sh) narrows the `composer.json` constraint with `composer update --with="behat/behat:^4"`, and removes `dmore/behat-chrome-extension`, which has no release that accepts Behat 4. That is why Behat 4 has no `chrome_headless` leg.

On Drupal 11 it also removes `dvdoug/behat-code-coverage`, which accepts Behat 4 only from 5.5. That release, and 5.4 before it, needs `phpunit/php-code-coverage` 12, while Drupal 11's `drupal/core-dev` holds the fixture on PHPUnit 11.5, which requires `^11.0.12`. The fixture therefore resolves 5.3.7, the newest release that still takes PHPUnit 11, and that one caps `behat/behat` at `^3`. The `composer.json` constraint is open at `^5.3.7`, so the repository root, running PHPUnit 12, does install 5.5 - only the Drupal 11 fixture is held back. [behat.php](behat.php) registers the coverage extension only when it is installed.

Every leg names the major it runs, as in `Test PHP 8.3, Drupal 11, Behat 3, Deps normal`, so a check name says what it covered without a lookup. The branch ruleset requires checks by name, so renaming a leg means updating the required checks on `4.x` to match.

`BEHAT` reaches the container through `ahoy`, so provisioning the fixture for Behat 4 locally is a matter of setting it. Run `ahoy provision` to switch back to Behat 3:

```bash
BEHAT=4 ahoy provision
ahoy test-bdd
```

### Drupal versions

Each major has its own fixture directory under [tests/behat/fixtures_drupal](tests/behat/fixtures_drupal), addressed as `d${DRUPAL_VERSION}`, and `DRUPAL_VERSION` defaults to `11` everywhere it is read. Renovate leaves Composer major updates alone, so moving to a new core major is a deliberate change rather than an automatic one.

Drupal 12 is pinned to `~12.0.0-alpha1` and runs 2 legs of its own - PHP 8.5, Behat 4, `normal` and `lowest` - so the `normal` / `lowest` pair covers both majors. Getting there takes a patched contrib set, because Drupal 12 and Symfony 8 broke most of what the fixture installs. See [Patched contrib](#patched-contrib).

Drupal 12 constrains its own grid hard:

- Drupal 12 requires PHP 8.5, so PHP 8.3 and PHP 8.4 are out.
- Drupal 12 requires Symfony 8, and `behat/behat` 3.33 - the newest Behat 3 - requires `symfony/yaml ^5.4 || ^6.4 || ^7.0`, so Behat 3 is out. `behat/behat` 4.0 accepts Symfony 8.
- `dmore/behat-chrome-extension` accepts Behat 3 only, so `chrome_headless` is out.
- Drupal 12 raises the database floor to MariaDB 10.11, which is why [docker-compose.yml](docker-compose.yml) runs `uselagoon/mariadb-10.11-drupal`. Drupal 11 asks for 10.6 or newer, so one image serves both majors.

Building the Drupal 12 fixture takes 3 packages that the Drupal 11 fixture does not:

- `mglaman/composer-drupal-lenient`, with every contrib module the fixture installs on its `extra.drupal-lenient.allowed-list`. Most of those modules have no release declaring `drupal/core ^12`, and the plugin strips the core constraint so they install anyway. The hosted lenient endpoint on drupal.org is not used - it currently redirects to a page that does not exist. A Composer plugin only shapes a solve it is already installed for, and the fixture has no solution until this one runs, so [scripts/provision.sh](scripts/provision.sh) installs it globally before the build update; Composer loads global plugins for local projects.
- `drush/drush ^14@dev`. No tagged Drush release accepts Symfony 8. This is why the fixture sets `minimum-stability` to `dev` with `prefer-stable`.
- `drupal/scheduled_transitions ^2.9.0@beta`, the first release declaring Drupal 12.

Every contrib module carries a floor in `d12/composer.json` at the oldest release known to work on Drupal 12. An older release predates the major and fails on it whatever the patches do - `drupal/token` at its lowest resolvable release declares no return type on `getSubscribedEvents()` - and a patch written against one release does not apply to another. Without the floors the `lowest` leg fails before a single scenario runs.

The floors cover contrib only. `lowest` still resolves the oldest usable version of the library's own dependencies, which is what those legs are for.

Relaxing the Composer solve is only half of it. Drupal reads `core_version_requirement` from each extension's `.info.yml` and refuses to enable one that excludes the running major, so after the update [scripts/provision.sh](scripts/provision.sh) appends `|| ^12` to that key across the installed contrib extensions. The rewrite touches the throwaway `build/` tree only, never the fixture sources.

Drupal 12 removes `contact`, `history` and `shortcut` from core, so `d12/config/sync` carries neither those modules nor the config that depended on them, and the `ModuleTrait` scenarios use `syslog` and `contextual`, which both majors ship.

### Patched contrib

Getting contrib installed is not the same as getting it to run. Drupal 12 and Symfony 8 between them broke 13 of the modules the fixture installs, so `d12/composer.json` carries a patch for each under `extra.patches`, applied by `cweagans/composer-patches`. The patches live in `d12/patches/` and fall into 5 groups:

| What changed | Modules |
| --- | --- |
| Drupal 12 removed the magic `original` property | `redirect`, `webform` |
| Symfony 8 removed `Request::get()` | `webform` |
| Drupal 12 removed annotation-only plugin discovery | `ctools`, `webform` |
| Drupal 12 and Symfony 8 tightened method signatures | `date_recur`, `dynamic_entity_reference`, `eck`, `entity_reference_revisions`, `paragraphs`, `pathauto`, `profile`, `redirect`, `scheduled_transitions`, `state_machine`, `time_field`, `webform` |
| Drupal 12 stopped discovering `template_preprocess_HOOK()` | `eck` |

Drupal 12 also deleted the Archiver plugin system, which `webform` injected but never used, and moved the `text_with_summary` field type out of `text` into a module of its own, which the fixture now requires and enables.

Every patch is generated against the released source rather than against the `build/` tree, which carries whatever the last provisioning applied. A patch generated from `build/` silently loses any hunk the tree already has.

A patch stops being needed the day its module ships a Drupal 12 release, at which point both the patch and the module's floor come out.

### Verifying the import landed

`drush cim` can enable the modules, abort on a fatal raised while it creates config entities, and still exit 0. The site then comes up with its modules enabled and none of the content types, fields, webforms or entity types the suite asserts on. [scripts/provision.sh](scripts/provision.sh) therefore reads a config entity only the fixture defines and fails when the import did not land, on every major - without that check a Drupal 12 build reports success on an empty site.

### Coverage

Coverage is not collected on the Drupal 12 legs. Drupal 12 brings PHPUnit 12, so `dvdoug/behat-code-coverage` 5.5 does install there and Behat 4 coverage becomes possible for the first time, but the coverage report stays on the settled Drupal 11 legs while core 12 is an alpha.
To take the Drupal 12 fixture as far as its legs do, set the 3 variables they set. `ahoy build` resets the containers, so the PHP version has to be on the build as well as the provisioning:

```bash
PHP_VERSION=8.5 DRUPAL_VERSION=12 BEHAT=4 ahoy build
```

That run installs Drupal 12 and then fails on the configuration check, which is the state this section describes.

## Updating fixture site

- Build the fixture site and make the required changes
- `ahoy drush cex -y`
- `ahoy update-fixtures` to copy configuration
  changes from build directory to the fixtures directory

### Validating and updating documentation

[docs.php](docs.php) is the only generator of reference documentation. One run
writes every generated region:

| Target | Holds |
| --- | --- |
| [STEPS.md](STEPS.md) and the index in [README.md](README.md) | The step vocabulary |
| [HELPERS.md](HELPERS.md) | The toolbox |
| [docs/configuration.md](docs/configuration.md) | The extension options and the tag reference |

The same run validates the [steps format](#steps-format), that every published
helper carries a summary, that tags resolve against `tag_registry()`, and that
every environment variable the source reads is documented.

```
ahoy update-docs  # Update documentation

ahoy lint-docs    # Check documentation for errors
```

An extension option and a tag are both generated from their definition in the
source, so neither can be added without appearing in the reference. Write the
option's `->info()` in
[BehatStepsExtension](src/Behat/ServiceContainer/BehatStepsExtension.php) and
the tag's description in `tag_registry()`, then run `ahoy update-docs`. Never
hand-edit inside a generated region.
