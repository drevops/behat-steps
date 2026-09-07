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
  - A method that overrides a Drupal Extension context method, such as `OverrideTrait::createNodes()` or `TaxonomyTrait::createTerms()`, is the one exception: an override binds by name, so it keeps the parent's. List it in `PARENT_OVERRIDES` in `TraitMethodNamingTest` instead of renaming it.

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

## Unsettled style questions

Four style questions have no dominant form in this codebase. Both sides of each are correct and behavior-identical where they appear, and converging any of them would churn 25 to 75 sites for no functional gain. Match the surrounding file and do not convert existing code from one form to the other as a drive-by change.

- **Nullable-object absence**: `if (!$element)` (~40 sites) and `=== NULL` (~35 sites) are both accepted. `is_null()` is not - it has been converged away.
- **Array emptiness**: `empty($array)` (~30 sites) and `$array === []` (~25 sites) are both accepted. Newer code leans strict, which is a weak preference rather than a rule.
- **`self::` versus `static::`**: not purely cosmetic. `static::` makes a static member overridable by a composing class, so it widens the public contract. `AccessibilityTrait` uses `static::` deliberately for that reason. Choose based on whether the member is intended as an extension seam, not on local consistency.
- **Docblock tag order and `@code` indentation**: `@param` before `@code` and the reverse both appear, as do flush and indented example bodies. `docs.php` renders `@code` bodies into [STEPS.md](STEPS.md), so changing indentation reflows the generated documentation.

Calling an instance method through `self::` or `static::` is not in this list - that was unambiguous and has been converged to `$this->`.

## Dependency policy

Keep the `require` section of `composer.json` minimal - it should contain only what **every** consumer needs regardless of which traits they use.

- **`require`**: the framework and browser abstraction that virtually all steps build on - `php`, `behat/behat`, `behat/mink`.
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

There are two types of tests in this repository: unit tests and Behat tests.

### Unit tests

Unit tests are run using PHPUnit installed in the root of the repository and
are independent of the Drupal version. This allows us to use the latest
features of PHPUnit.

```bash
ahoy test-unit          # Run all unit tests

ahoy test-unit-coverage # Run tests with code coverage
```

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
