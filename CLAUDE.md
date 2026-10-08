# Behat Steps - Claude Memory

## Project Context
This repository contains Behat step definitions for PHP projects (with specialized support for Drupal), organized as traits that can be included in Behat contexts. The steps provide reusable testing functionality for generic PHP components and Drupal-specific features.

## Source files location

Source files are located in the `src` directory. Each trait is organized into a separate file, and the steps are defined within those files.

The step vocabulary lives under `src/Steps/`, split into `Web/` (`DrevOps\BehatSteps\Steps\Web`) and `Drupal/` (`DrevOps\BehatSteps\Steps\Drupal`). The directory a trait sits in is its context, and `docs.php` reads it from there. Placement follows the subject: `Web/` holds steps that mean something on any site, and `Drupal/` holds steps that only mean something on a Drupal site, whether they reach a backend or only drive the page, as `BatchTrait` and `BigPipeTrait` do. The helper traits under `src/Helper/` split the same way, into `Web/` and `Drupal/`, so a trait's half is legible from its path. The backend layer under `src/Backend/` and both helper halves are library code, not vocabulary.

Every type under `src/Behat/` sits in the sub-namespace named for its role or concern, never at its root, and `Tag` is the 1 exception because every layer reads tags through it. `ClassNamingTest` fails any other root type. The contracts the context initializer injects through (`BackendAwareInterface`, `ParametersAwareInterface`, `UserAwareInterface`) sit in `Context/`; `ParametersTrait` sits in `Config/` and `MinkAwareTrait` in `Mink/`, so no container service imports `Context/`.

`src/Behat/Context/` is one chain: `WebRawContext` carries the plumbing and 3 of the web helper traits without registering steps, `WebContext` extends it and composes every trait under `src/Steps/Web/`, and `DrupalContext` extends that and composes every trait under `src/Steps/Drupal/`.

A step trait composes the helper traits it needs itself, so a trait that creates entities brings the teardown that removes them and a context composing no such trait runs no teardown. `src/Helper/` is a library of concerns, not a catch-all: `Drupal/` holds `EntityLifecycleTrait`, `AuthTrait`, `StaticCacheTrait`, `FixtureFileTrait` and `QueryTrait`; `Web/` holds `FixtureDirectoryTrait`, `HeadingTrait`, `JavascriptErrorTrait`, `LastStepTrait`, `RequestHeadersTrait`, `StringTrait`, `TableTransposeTrait` and `TokenTrait`. Every helper member carries its trait's prefix, so a name cannot collide once flattened into a context.

Step traits never `use` other step traits. Shared logic belongs in a helper trait under `src/Helper/` named for its concern, composed by whichever step traits and contexts need it.

A trait's directory says what it is: `src/Steps/` registers Gherkin, `src/Helper/` registers none. `scripts/lint-traits.php` holds a step trait to composing no other step trait, and a helper trait to registering no Gherkin. A helper may register a hook, because the trait that owns a teardown carries the hook that runs it.

3 terms stay apart in identifiers, docblocks and prose. A *backend* is what a step resolves a capability from under `src/Backend` (Drupal, Drush or Blackbox), reached with `backendFor()`. A *browser driver* is Mink's driver behind the session, reached with `browserDriverFor()`, and is never called a "Mink driver". "Driver" in one of our identifiers only ever means Mink's. An *HTTP client* is the page, detached or bare client on `WebRawContext`, and is neither. See the "Backend, browser driver and HTTP client" section of [CONTRIBUTING.md](CONTRIBUTING.md).

Every trait that calls a method it does not declare carries a `@phpstan-require-extends` annotation naming the base class that provides it: `Behat\MinkExtension\Context\RawMinkContext` when a Mink session is all it touches, and `DrevOps\BehatSteps\Behat\Context\WebRawContext` when it reaches a backend or the extension configuration. A trait that calls nothing outside itself carries none. A context that composes `AuthTrait` declares `\DrevOps\BehatSteps\Behat\Context\UserAwareInterface` so the initializer injects the user registry and the authenticator.

A trait answers 3 questions before it acts, each with 1 mechanism, and they are never merged: opt-in (`skipTag(__TRAIT__, $scope)`, reading the `enabled` option and the skip tag), activation (the trait's own tag, such as `@testmode`), and prerequisites (declared in `<prefix>Prerequisites()` through backend capabilities, checked with `assertPrerequisites(__TRAIT__)`, a hook passing its `$scope` as well, or `prerequisitesMet(__TRAIT__)` in a teardown). Opted out returns quietly; an unmet prerequisite fails the scenario. A hook checks prerequisites at scenario start, a step as it runs. Module state goes through `ModuleCapabilityInterface`, never `moduleExists()`. See "Deciding whether a trait acts" in [CONTRIBUTING.md](CONTRIBUTING.md).


## Installation & Requirements for consuming this library
```bash
composer require --dev drevops/behat-steps:^3
```

## Development of this project

> **Note:** These commands are for developing this Behat steps library itself, not for using it in your project.

### Development Commands
- `ahoy build` - Setup a fixture Drupal site in the `build` directory
- `ahoy test-bdd` - Run all BDD tests
- `ahoy test-bdd path/to/file` - Run all scenarios in specific feature file
- `ahoy test-bdd -- --tags=wip` - Run all scenarios tagged with `@wip` tag
- `ahoy debug` - Enable debugging
- `ahoy drush cex -y` - Export configuration
- `ahoy update-fixtures` - Update fixture files for Drupal from the current build
- `ahoy copy-files` - Update fixture files to the current build
- `ahoy update-docs` - Update documentation
- `ahoy lint-docs` - Check documentation for errors
- `ahoy lint` - Run linting
- `ahoy lint-fix` - Fix linting issues
- `ahoy test-unit` - Run unit tests

### Fixture Files Management

Static fixture files live in `tests/behat/fixtures/`.

Traits without a Drupal dependency read them from a PHP built-in server: tag the
scenario `@phpserver` and address the file as `http://cli:8888/<file>`. The
server serves `tests/behat/fixtures/` at its root, so an edited fixture applies
on the next run.

Drupal traits read them through the fixture site, which receives a copy of the
directory in `build/web/sites/default/files/` during provisioning. After editing
a fixture that a Drupal-served scenario reaches, copy it across:

```bash
ahoy copy-files
```

## Steps Format Guidelines
- **General Guidelines**:
  - Use tuple format instead of regular expressions
  - Use descriptive placeholder names
  - Use `the following` for tabled content
  - Use `with` for properties: `Then the link with the title :title should exist`
  - Avoid optional words like `(the|a)`, except the `(s)` plural token, which a
    noun agreeing with a count or a list keeps: `:count row(s)`, `the role(s)
    :roles`
  - Omit unnecessary suffixes like `on the page`
  - Method names should begin with the trait name: `userAssertHasRoles()`

- **Placeholder Names**: one concept gets one name across every trait. Reusing a
  name that already exists is always preferred over inventing a synonym.
  - Placeholder names are `snake_case` and match the method parameter name
    exactly - Behat binds the argument by name, so a mismatch only surfaces at
    run time
  - A step that names its target (`:element`, `:path`, `:key`, `:field`)
    compares against `:value`; `:text` is only for steps asserting on a whole
    body with no named target, such as `the modal should contain :text`
  - A bundle placeholder is named after its entity type - `:content_type`,
    `:media_type`, `:content_block_type`, `:vocabulary` - unless the step is
    deliberately entity-agnostic, where `:bundle` is correct
  - A partial match reads `:partial_<thing>`, as in `:partial_name`
  - An email address is `:address` and a 1-based position is `:index`

- **Articles and Word Order**:
  - Every noun takes an article, and `URL` is uppercase
  - A named value reads `the value :value`, never `the :value value` or a bare
    `:value`
  - A bundle placeholder qualifying an entity noun comes before it
    (`the :media_type media`); one that is itself the subject follows its noun
    (`the media type :media_type`)
  - Any other placeholder that names a thing follows its noun: `the queue :queue`, `the module :module`, `the region :region`. Only a count before its unit (`:count item(s)`) and a closed-set qualifier (`the :enabled_or_disabled state`, `in :direction order`, `a REST :method request`) also come first
  - A step never opens with a placeholder: `the :content_type content with the title :title should not exist`
  - `ahoy lint-docs` rejects a placeholder followed by a word repeating its name (`:queue queue`), a `:value` not reading `the value :value`, a placeholder after `containing` without the `partial_` prefix, `:text` compared against a named target, a step opening with a placeholder, the names in `docs.php`'s `non_descriptive_placeholders()` and `placeholder_synonyms()`, the phrases in `rejected_step_phrases()`, an `I visit` step that names its destination in words but no page or link, a `Then` step that opens with `no`, and a qualifier ending a `Then` step after `exist` or `be <state>`

- **Settled Wording**: each idea reads 1 way
  - A step that opens a page reads `I visit the ... page` and names the page: `I visit the :content_type content edit page with the title :title`, never `I edit the ...`
  - Its method opens with `Visit` and names the page as the step does: `contentVisitEditPageWithTitle()`, `userVisitProfileEditPage()`. `TraitMethodNamingTest` enforces it
  - A click reads `I click on the ...`, the viewport is `the viewport`, and a `<select>` is `the select :selector`
  - A second `with` qualifier on an action repeats `With` in the method name, in step-text order: `emailFollowLinkWithIndexWithSubjectContaining()`
  - An assertion negates with `should not`, never with the determiner `no` or the perfect tense: `an email should not be sent`, not `no emails should have been sent`

- **Given Steps**:
  - Define test prerequisites
  - State a fact in the present tense: `<subject> <verb> <complement>`. A
    verbless step, a bare noun phrase, or the perfect tense (`has been cleared`)
    is not a Given
  - Use words like `exists` or `have`; `is`/`are` plus an adjective is also
    correct where it mirrors the matching `should be ...` assertion
  - Avoid using `should` or `should not`
  - Never refer to the person: no `I`, `my`, `me`, `we`, `us` or `our` anywhere in the step

- **When Steps**:
  - Describe an action with an action verb
  - Use the format `When I <verb>` - the step must start with `I` followed by a space

- **Then Steps**:
  - Specify assertions and expectations
  - Use `should` and `should not` for assertions
  - Start with the entity being asserted
  - Never refer to the person: no `I`, `my`, `me`, `we`, `us` or `our` anywhere in the step
  - A qualifier that narrows the asserted entity reads with it, before `should`: `the link :link in the region :region should exist`. A qualifier of the predicate's object stays with it (`should match the XSD schema in the file :filename`), and a table or PyString argument still comes last: `the meta tag should exist with the following attributes:`
  - Methods should include the `Assert` prefix

## Exception Types

Which exception a step throws is part of the public contract - consumers catch on it, and the `@test-trait:` harness asserts on it. Pick the type by what failed, never by what the surrounding code happens to use:

| Failure | Throw |
| --- | --- |
| An assertion failed and the trait has a Mink session | `Behat\Mink\Exception\ExpectationException`, with `$this->getSession()->getDriver()` as the second argument |
| An element the step locates is missing - on the page or in an XML response: a field, link, button, select, table or row | `Behat\Mink\Exception\ElementNotFoundException` (a subclass of `ExpectationException`) |
| An attribute, a JSON path or a table column is missing - none of them is an element | `Behat\Mink\Exception\ExpectationException` |
| An assertion failed and the trait has no Mink session | `DrevOps\BehatSteps\Exception\AssertionException` |
| Not an assertion: an invalid step argument, an unmet prerequisite, an infrastructure error | `\RuntimeException` |
| The current browser driver lacks a required capability | `Behat\Mink\Exception\UnsupportedDriverActionException` |
| No backend the scenario lists provides a required capability | `DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException` |

Never throw plain `\Exception` or `\InvalidArgumentException` from `src/`.

A failure message names its subject the way its step does, with the article (`The element "..."`, `The config "..." with the key "..."`), or keeps its quantifier when nothing in a set matched (`No hreflang alternate links were found on the page.`), quotes every value it names, the page URL included, ends with a period, and closes a broken expectation with `, but it should not` or `, but it should be`. See "Failure messages" in [CONTRIBUTING.md](CONTRIBUTING.md).

A trait without a Mink session is one that never calls `$this->getSession()`. The ones that assert are `Steps\Web\CommandTrait`, `Steps\Drupal\ConfigTrait`, `Steps\Drupal\DrushTrait`, `Steps\Drupal\ModuleTrait`, `Steps\Drupal\QueueTrait`, `Steps\Drupal\RedirectTrait`, `Steps\Drupal\StateTrait` and `Steps\Drupal\WatchdogTrait`. Do not add a session to a trait just to reach `ExpectationException`.

In `@test-trait:` scenarios, `Then it should fail with an error:` asserts an assertion exception and `Then it should fail with an exception:` asserts a `\RuntimeException`. Use `Then it should fail with a "<class>" exception:` only when the specific class matters.

## Common Behat Step Patterns
- Block assertions:
  - `the block "..." should exist`
  - `the block "..." in the region "..." should exist`

- Content block operations:
  - `the content block type "..." should exist`
  - `the following "..." content blocks exist:`
  - `I visit the "..." content block edit page with the description "..."`

- Email testing:
  - `I enable the test email system`
  - `I clear the test email system queue`
  - `an email should be sent to the address "..."`

## Skipping Trait Hooks
A trait's hooks are switched off by adding the `@behat-steps-skip:TRAIT_NAME` tag to a scenario or a feature. The tag names the trait, never a hook method, and switches off every hook the trait registers. A skip tag whose value is not shaped like a trait name, such as a hook method, fails the run at scenario start.

Example: To skip the hooks of `EmailTrait`, add `@behat-steps-skip:EmailTrait` tag to the feature.

A scenario hook opens with `if ($this->skipTag(__TRAIT__, $scope)) { return; }`, with only resets of the trait's own properties before it. A trait whose step hooks or transforms need the answer records it in a property instead: `$this->dateEnabled = !$this->skipTag(__TRAIT__, $scope);`. A scenario hook with nothing to switch off carries no guard and is listed with its reason in `UNGUARDED_HOOKS` in `tests/phpunit/src/SkipGuardTest.php`. See the "Skipping a trait's hooks" section of [CONTRIBUTING.md](CONTRIBUTING.md).

## Reading Tags
Never call `hasTag()` or `getTags()` directly. Behat 3 strips the `@` from a tag by default and Behat 4 keeps it, and `hasTag()` compares strictly, so a bare-name comparison that matches on one major fails on the other. Read tags through `DrevOps\BehatSteps\Behat\Tag` instead. A trait reads a tag only through `Tag::has($scope, self::X_TAG)` for a flag, `Tag::values($scope, self::X_TAG)` for the values of `@x:VALUE` tags, and `Tag::valueStates($scope, self::X_TAG)` for the on/off state of each value of `@x:VALUE`/`@x:!VALUE` tags. Passing the scope reads the scenario plus its feature, so a tag on the `Feature:` line applies to every scenario below it; a single node is passed only by a hook that ranks the 2 lines itself. Every tag a trait reads is named in a prefixed constant (`TESTMODE_TAG`) and listed in `tag_registry()` in `docs.php`, never written as a string literal; `Tag::JAVASCRIPT` names Mink's `@javascript`, the only tag a hook attribute filters on (`#[BeforeScenario('@javascript')]`); a hook reads any other tag in its body. `TagReadTest` and `DocsTest` enforce this. `Tag::all()`, `Tag::on()` and `Tag::normalize()` return raw lists for code outside the traits. Nothing outside `Tag` calls `getTags()` or `hasTag()`. See the "Reading tags" section of [CONTRIBUTING.md](CONTRIBUTING.md).

## Code Style Conventions
- Code is written using Drupal coding standards
- Local variables and method arguments: `snake_case`
- Method names and class properties: `camelCase`
- Line breaks: `"\n"` to split or match text the code received, `PHP_EOL` for console output, messages and generated docs. A break spliced into a nowdoc or heredoc template stays `"\n"`
- Paths are built with `/`. `DIRECTORY_SEPARATOR` only compares against, splits or trims a path the platform returned (`realpath()`, `getcwd()`, `getFileName()`, a directory iterator)
- A closure that never touches `$this` is `static` (`SlevomatCodingStandard.Functions.StaticClosure`), and an empty class or function body is `{}` (`EmptyBodyTest`)
- A single-word boolean takes `is_` (`$is_found`). A caught exception is `$exception`, a directory `$directory`, a restored value `$original`, a test's expected message `$expected_message`, and a dynamic call is `->{$method}()`
- A concrete class under `src/` is `final` unless a project extends it. The extension points (the contexts, the backends, `Core`, the field handlers, the browser adapters, `HttpClientFactory` and `DocumentElement`) are listed in `EXTENSION_POINTS` in `ExtensionPointTest`, and a final class keeps its members `protected`. See "Final classes" in [CONTRIBUTING.md](CONTRIBUTING.md)
- Every constant under `src/` declares a native type, `public const string NAME = 'value';` (`TypedConstantTest`)
- The full list is "Settled style questions" in [CONTRIBUTING.md](CONTRIBUTING.md)

## Documentation
- List of all available steps is produced from the traits' step attributes and docblock comments and exported into [STEPS.md](STEPS.md)

### Updating Steps Documentation
The [STEPS.md](STEPS.md) documentation is automatically generated from the source code using the [docs.php](docs.php) file. After making changes to step definitions or adding new ones, you should regenerate the documentation:

1. Using Ahoy (recommended):
   ```bash
   ahoy update-docs
   ```

2. Direct PHP execution:
   ```bash
   php docs.php
   ```

3. Linting the documentation:
   ```bash
   ahoy lint-docs
   ```

This ensures that the documentation remains in sync with the actual code implementation.

### Architecture Documentation

[docs/architecture/README.md](docs/architecture/README.md) is the narrative walkthrough of how the project works - components, primary flows, and the diagrams that go with them. The diagrams are PlantUML sources (`docs/architecture/*.puml`) rendered to committed light SVGs; both are tracked. It is owned by the `update-architecture-docs` skill in [.claude/skills/update-architecture-docs/SKILL.md](.claude/skills/update-architecture-docs/SKILL.md), which holds the rules for writing it. Do not hand-edit it against those rules; invoke the skill.

**Regenerate it before opening any pull request.** Run the skill (say "update architecture docs") as part of the pre-PR checks, alongside `ahoy update-docs` and `ahoy lint`. When the branch changed nothing structural, the skill is a no-op and the document stays as it is - that is the expected outcome for most PRs, and it is still worth the check.

After editing any `.puml`, re-render every SVG so the sources and the renders cannot drift:

```bash
plantuml -tsvg docs/architecture/*.puml
```

A change is structural when it moves, adds, or removes a component or alters a flow between components: a new trait directory or namespace, a change to how a shipped context composes traits, a change to how `docs.php` discovers or renders steps, a change to how the fixture site is provisioned, a change to the nested-Behat harness, or a change to the CI matrix. Adding a step to an existing trait, renaming step text, or fixing an assertion is not structural.

## Implementation Patterns and Learnings

### Working with Drupal Compound Fields
- Drupal compound fields (like datetime, daterange) have multiple sub-inputs that cannot be targeted with standard `findField()`
- Use XPath-based selectors to locate specific sub-inputs within compound fields
- Implement fallback strategies: try label elements first, then span elements, then generic class-based searches
- Compound field structure example: `field_name[0][value][date]` and `field_name[0][value][time]`
- Date range fields use `[value]` for start and `[end_value]` for end components

### Field Configuration Management
- New field configurations must be added to **every** fixture under `tests/behat/fixtures_drupal/`
- Field configs include: field storage, field instance, and form display updates
- When adding fields, update `core.entity_form_display.node.page.default.yml` with:
  - Field references in dependencies config section
  - Module dependencies (e.g., `datetime`, `datetime_range`)
  - Widget configuration with type, weight, region, and settings
- After creating configs in `build/config/sync`, copy to every fixture directory
- Use `ahoy drush cim -y` to import configurations into the build environment
- A contrib module added to `d12/composer.json` is also added to its `extra.drupal-lenient.allowed-list`, together with any contrib module it pulls in transitively - most contrib has no Drupal 12 release, and the fixture installs it through `mglaman/composer-drupal-lenient`

### Test Organization and Tagging
- Every registered step needs at least one scenario that runs it - `tests/phpunit/src/StepScenarioCoverageTest.php` fails `ahoy test-unit` on a step no scenario reaches. A step run inside a `@test-trait` scenario's nested run counts; a step only in a `@test-skipped` scenario does not
- Consolidate related tests into existing feature files rather than creating new ones
- Use descriptive tags (e.g., `@datetime`) to allow selective test execution
- Negative tests using `@test-trait:FieldTrait` should use simple navigation (e.g., `I go to "node/add/page"`)
- Avoid using custom steps in negative tests that may not be available in BehatCLI context
- Test-only tags - ones consumed by the test harness (the test contexts or the bootstrap traits) to configure a scenario, as opposed to the library's public tags registered in `docs.php`'s `tag_registry()` - must be prefixed with `test-` (e.g., `@test-bigpipe-timeout`) so they are clearly distinguishable from real library tags.
- The harness in `tests/behat/bootstrap/` reads tags, parses numbers, names hooks and types constants the way `src/` does, but its steps keep their own wording, placeholders and signatures. See "Behat harness conventions" in [CONTRIBUTING.md](CONTRIBUTING.md).

### Unit Test Conventions
- A unit test extends `UnitTestCase` (a kernel test extends `KernelTestBase`), writes fixtures through `writeFixture()` into `static::$tmp`, and reflects a class held in a variable through `static::reflect()`; a `::class` constant goes to `new \ReflectionClass()`
- Static members are reached through `static::`; `self::` stays only in a constant expression, in a final or anonymous class, or on a final or private member, such as PHPUnit's `self::fail()`
- A test helper opens with a verb from `TestConventionTest::HELPER_VERBS`; a yes-or-no helper opens with `is` or `has`
- A test double's test-only methods are `call<Method>()` to run a protected method and `test<Accessor>()` to read or write protected state; everything else on a double overrides a production method
- A double is named `<Trait>TestImplementation` or `<Qualifier><Role>`, and `Test` or `Testable` is never the qualifier
- `tests/phpunit/src/TestConventionTest.php` enforces all of these; the "Test suite conventions" section of [CONTRIBUTING.md](CONTRIBUTING.md) explains them

### Step Definition Constraints
- Documentation tool (`docs.php`) rejects a method carrying more than 1 step attribute (`#[Given]`, `#[When]` or `#[Then]`)
- Use a single step attribute and document alternative usage in `@code` examples
- A step method takes exactly the arguments its step binds: a required `string` named after each placeholder, plus a trailing `TableNode` or `PyStringNode` for a step ending in a colon. No default, nullable, numeric or `mixed` parameter, and no flag only PHP callers pass - `tests/phpunit/src/StepArgumentTest.php` enforces it
- A step parses a numeric placeholder with `stringParseInteger()` or `stringParseNumber()` from `Helper\Web\StringTrait`, which throw `\RuntimeException` naming the argument. It passes the smallest value it accepts as `$min`, unless the helper it hands the value to checks the range itself, as `elementGetNth()` does
- An optional parameter belongs on a helper, never on a step method. A string parameter left out for "not given" defaults to `NULL` with a nullable type, never to an empty string, while a default that is a real value, such as `$binary = 'drush'`, stays - `tests/phpunit/src/OptionalParameterTest.php` rejects an empty-string default anywhere under `src/`
- A step body is a thin wrapper over named helpers: it parses its arguments, calls helpers and guards on what they return. No step calls another step of its trait, holds a `switch`, `match`, `try` or closure, or runs a loop doing more than guarding and 1 call, and none runs more than 4 statements besides its guards - `tests/phpunit/src/StepBodyTest.php` enforces it. See "Thin step bodies" in [CONTRIBUTING.md](CONTRIBUTING.md)
- Always provide both imperative (content) and continuous (activeForm) task descriptions

### Public surface
- A `public` helper is published in [HELPERS.md](HELPERS.md) and covered by semantic versioning; a `protected` one promises nothing
- A job published in 1 trait is published in every trait that does it: `xmlGetFirstNode()` is public because `jsonGetValue()` is. A helper serving only its own trait's machinery stays protected
- A method whose docblock invites an override (`Override to ...`, `Override when ...`, `overrides this method`) is public - `tests/phpunit/src/PublicSurfaceTest.php` fails a protected one
- A lookup is named for what a miss does: `Find` returns `NULL`, `Get` throws, `Load` returns a set. `Read` only reads a file's contents, as `fixtureDirectoryReadFile()` does, and a helper that removes what it returns names the removal, as `watchdogClearErrors()` does

### Lookups When Several Entities Match
- A lookup by title, label, name or description that returns 1 entity returns the newest match: `queryFindNewestEntityId()` for a content entity (the highest serial ID, so a re-saved older entity never wins) and `entityLifecycleFindNewest()` for a config entity such as a block or a menu (the one the scenario created last, otherwise the last machine name in natural order). Never pick with `reset()`, `current()`, `end()` or a sort of your own
- A `... does not exist` step deletes every match, and a step that only checks whether an entity exists checks every match. See "Lookups" in [CONTRIBUTING.md](CONTRIBUTING.md)

### Nested PyStrings in @test-trait Scenarios
When writing @test-trait scenarios that test BehatCliContext functionality (tests that run Behat within Behat), nested PyStrings are required when the inner scenario steps themselves accept PyString arguments.

**Problem**: Standard escaped PyString delimiters `\"\"\"` don't work because:
1. Gherkin parser captures the outer PyString as literal text including the escaped quotes
2. BehatCliContext writes this literal text to a generated feature file
3. Gherkin parser fails when parsing the generated file with malformed PyString syntax

**Solution**: Use **triple single quotes `'''`** for inner PyStrings:
```gherkin
@test-trait:SomeTrait
Scenario: Test error condition
  Given some behat configuration
  And scenario steps tagged with "@email":
    """
    When I send test email to "test@example.com" with:
      '''
      Email body content here
      '''
    Then an email should be sent to the address "test@example.com"
    """
```

**How it works**: `BehatCliTrait::behatCliWriteScenarioSteps()` converts `'''` → `"""` after extracting the PyString but before writing the generated feature file, ensuring proper Gherkin syntax.

**Example test**: See the "Test nested PyStrings using triple single quotes" scenario in `tests/behat/features/behatcli.feature` for a demonstration of nested PyStrings.

### Coverage Reports: Two Files to Always Check

**CRITICAL**: When running `ahoy test-bdd-coverage <path>`, TWO separate cobertura.xml files are generated:

1. **`.logs/coverage/behat/cobertura.xml`**
   - Contains coverage from the **main run only** - every scenario Behat runs directly, without the nested `@test-trait` runs
   - Shows what regular Behat scenarios cover
   - Example: EmailTrait shows 83.63%

2. **`.logs/coverage/behat_cli/cobertura.xml`**
   - Contains **MERGED coverage** (main run + `@test-trait` subprocess runs)
   - This is the **TRUE total coverage** to report
   - Example: EmailTrait shows 90.06% (correctly higher)

**How it works**:
- During test execution, @test-trait scenarios spawn subprocess Behat runs
- Each subprocess generates a coverage file in `.logs/coverage/behat_cli/phpcov/*.php`
- After tests complete, `scripts/merge-coverage.php` merges all subprocess coverage with the main behat coverage
- The merged result is written to `behat_cli/cobertura.xml`

**Important**: The `ahoy test-bdd-coverage` command automatically cleans up old subprocess coverage files before running tests to prevent pollution from stale data. Always check the `behat_cli/cobertura.xml` (merged) file for the accurate total coverage percentage.

**Assessing coverage after running tests**:

**RECOMMENDED**: Use the `scripts/check-coverage.php` script for easy coverage assessment:
```bash
# Run tests with coverage
ahoy test-bdd-coverage tests/behat/features/some_feature.feature

# Check coverage using the script (uses MERGED coverage by default)
php scripts/check-coverage.php SomeTrait

# Output shows:
# Class: DrevOps\BehatSteps\Steps\Web\SomeTrait
# Line rate: 0.95901639344262 (95.90%)
#
# Uncovered lines:
# 47, 50, 210, 259, 491
```

**Manual method** (if script is not available):
```bash
# Run tests with coverage
ahoy test-bdd-coverage tests/behat/features/some_feature.feature

# Check main-run coverage
grep 'class name="DrevOps\\BehatSteps\\Steps\\Web\\SomeTrait"' .logs/coverage/behat/cobertura.xml | grep -o 'line-rate="[^"]*"'

# Check MERGED coverage (THIS IS THE TRUE COVERAGE)
grep 'class name="DrevOps\\BehatSteps\\Steps\\Web\\SomeTrait"' .logs/coverage/behat_cli/cobertura.xml | grep -o 'line-rate="[^"]*"'
```

**Coverage Check Script Usage**:
```bash
# Check coverage for a trait (uses merged coverage by default)
php scripts/check-coverage.php <TraitName>

# Check coverage using a specific coverage file
php scripts/check-coverage.php <TraitName> <path/to/cobertura.xml>

# Examples:
php scripts/check-coverage.php ElementTrait
php scripts/check-coverage.php ResponsiveTrait .logs/coverage/behat/cobertura.xml
```

**IMPORTANT**: Always use `php scripts/check-coverage.php` when you need to assess coverage for a trait. This script:
- Automatically uses the correct merged coverage file (`.logs/coverage/behat_cli/cobertura.xml`)
- Shows the line rate as both decimal and percentage
- Lists all uncovered line numbers
- Handles both Docker and local path conventions

### Field Naming Conventions
- Use descriptive field names without "test" prefix (e.g., `field_datetime` not `field_test_datetime`)
- Field labels should be user-friendly: "Event date", "Event period", etc.
- Machine names follow Drupal conventions: `field_{description}`

### Documentation and Code Quality
- Always run `ahoy update-docs` after adding/modifying step definitions
- Use `ahoy lint-docs` to verify documentation format
- Run `ahoy lint` to ensure code passes all quality checks (Composer validation, parallel-lint, PHPCS, PHPStan, Rector, Gherkinlint and the layer and trait linters)
- Run BDD tests with specific tags during development: `ahoy test-bdd -- --tags="@tagname"`

**IMPORTANT: Never Modify Code Quality Tool Configurations**
- **NEVER** modify `phpstan.neon`, `phpcs.xml`, `rector.php`, or other code quality tool configs to make lint pass
- If linting errors occur, fix the actual code to address the issues
- Do not add ignores or suppressions to configuration files
- If errors seem legitimate and can't be fixed, ask for guidance instead
- Code quality standards must be maintained consistently across the project
