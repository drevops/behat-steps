# Contributing

Below are some guidelines for developing and maintaining the Behat steps.

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

## Member ordering within a trait

Traits lay their members out in this order:

1. Trait composition (`use`), then constants, then properties.
2. Hooks (`#[BeforeScenario]`, `#[AfterStep]` and the like).
3. `Given` steps, then `When` steps, then `Then` steps.
4. Other public methods.
5. Protected helpers.

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
- **The in-process driver.** Every other trait under `src/Steps/Drupal` reaches Drupal's API directly. Each such method calls `RawContext::drupal()` first, which bootstraps the in-process driver and returns it, or throws a `BootstrapException` naming the requirement when the scenario is running on the Blackbox or Drush driver. Those scenarios carry `@api`.

A new step that touches `\Drupal::` calls `$this->drupal();` as its first statement. That is the only sanctioned bootstrap: nothing else may assume the container exists.

[scripts/lint-layers.php](scripts/lint-layers.php) holds the lower boundary. It reads every file under `src/Driver` and fails on any code reference into the `Behat` or `Mink` namespaces: imports, type declarations, and class names reached through a string. A prose mention in a comment is fine - it's the code references that matter. `ahoy lint` runs it.

## Behat 4 readiness

`src/Behat` plugs into 4 Behat extension points, and each one is written to satisfy Behat 3.32 and Behat 4 at the same time. Keep it that way when touching them.

- **Signatures are typed for Behat 4, widened for Behat 3.** Behat 4 types its interfaces where 3.32 leaves them untyped, so implementations declare the Behat 4 return type (`ClassGenerator::supportsSuiteAndClass(): bool`, `HookScope::getName(): string`, `FilterableHook::filterMatches(): bool`, `Extension::getConfigKey(): string`) and keep the parameter untyped or `mixed` so the 3.32 interface is not narrowed.
- **`DriverListener` reads the event, not the removed interface.** Behat 4 drops `ScenarioLikeTested`. Both `ScenarioTested::BEFORE` and `ExampleTested::BEFORE` carry a `BeforeScenarioTested`, which declares `getFeature()` and `getScenario()` itself in both versions, so the listener type-hints that class.
- **`HookAttributeReader` builds its callable through Behat's factory when there is one.** Behat 4 types the callee constructor as `callable`, and `[class-string, method]` is not callable for an instance method. `ContextMethodCallableFactory` wraps such methods on Behat 4 and is absent on Behat 3, so `makeCallable()` uses it only when the class exists.
- **The `context.class_generator.simple` override survives by service id.** Behat collects generators by tag before an activated extension's `process()` runs and injects them as references, so replacing the definition behind that id swaps the class in both versions.

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

## Updating fixture site

- Build the fixture site and make the required changes
- `ahoy drush cex -y`
- `ahoy update-fixtures` to copy configuration
  changes from build directory to the fixtures directory

### Validating and updating documentation

The [available steps](STEPS.md) documentation is generated automatically from
the source code.

The [steps format](#steps-format) is validated as well.

```
ahoy update-docs  # Update documentation

ahoy lint-docs    # Check documentation for errors
```
