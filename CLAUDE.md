# Behat Steps - Claude Memory

## Project Context
This repository contains Behat step definitions for PHP projects (with specialized support for Drupal), organized as traits that can be included in Behat contexts. The steps provide reusable testing functionality for generic PHP components and Drupal-specific features.

## Where the rules live

[CONTRIBUTING.md](CONTRIBUTING.md) holds the rules for code in this repository, and this file doesn't repeat them. Read the sections a change touches before making it:

- Adding, rewording or renaming a step: [Steps format](CONTRIBUTING.md#steps-format), [Step arguments](CONTRIBUTING.md#step-arguments), [Method naming conventions](CONTRIBUTING.md#method-naming-conventions) and [Behat tests](CONTRIBUTING.md#behat-tests).
- Writing a step body or a helper: [Thin step bodies](CONTRIBUTING.md#thin-step-bodies), [The helper API](CONTRIBUTING.md#the-helper-api), [The same job has the same visibility](CONTRIBUTING.md#the-same-job-has-the-same-visibility), [Method naming conventions](CONTRIBUTING.md#method-naming-conventions) and [Lookups](CONTRIBUTING.md#lookups).
- Throwing from a step: [Exception Types](#exception-types) below and [Failure messages](CONTRIBUTING.md#failure-messages).
- Adding or moving a trait, a class or a namespace: [Layers](CONTRIBUTING.md#layers), [Class naming conventions](CONTRIBUTING.md#class-naming-conventions), [Final classes](CONTRIBUTING.md#final-classes) and [Member ordering within a trait](CONTRIBUTING.md#member-ordering-within-a-trait).
- Naming a backend, a browser driver or an HTTP client, in code or in prose: [Backend, browser driver and HTTP client](CONTRIBUTING.md#backend-browser-driver-and-http-client).
- Adding a hook, reading a tag or declaring a prerequisite: [Reading tags](CONTRIBUTING.md#reading-tags), [Skipping a trait's hooks](CONTRIBUTING.md#skipping-a-traits-hooks) and [Deciding whether a trait acts](CONTRIBUTING.md#deciding-whether-a-trait-acts).
- Reaching a backend or sending a request: [What a trait needs from the backend](CONTRIBUTING.md#what-a-trait-needs-from-the-backend) and [Sending a request from a trait](CONTRIBUTING.md#sending-a-request-from-a-trait).
- Changing a Behat extension point under `src/Behat`, or `behat.php`: [Behat 4 readiness](CONTRIBUTING.md#behat-4-readiness).
- Writing a unit, kernel or Behat test: [Running tests](CONTRIBUTING.md#running-tests), [Test suite conventions](CONTRIBUTING.md#test-suite-conventions), [Behat harness conventions](CONTRIBUTING.md#behat-harness-conventions) and [Coverage markers](CONTRIBUTING.md#coverage-markers).
- Editing a fixture file or the fixture site: [Static fixtures](CONTRIBUTING.md#static-fixtures), [Updating fixture site](CONTRIBUTING.md#updating-fixture-site) and [Drupal versions](CONTRIBUTING.md#drupal-versions).
- Changing `docs.php` or a generated document: [Validating and updating documentation](CONTRIBUTING.md#validating-and-updating-documentation).
- Adding a dependency: [Dependency policy](CONTRIBUTING.md#dependency-policy).
- Reading a CI failure: [Continuous integration](CONTRIBUTING.md#continuous-integration).
- Any other style question: [Settled style questions](CONTRIBUTING.md#settled-style-questions) and [Unsettled style questions](CONTRIBUTING.md#unsettled-style-questions).

A new rule goes in `CONTRIBUTING.md`, and this file links to it rather than repeating it.

## Trait hosts

Every trait that calls a method it does not declare carries a `@phpstan-require-extends` annotation naming the base class that provides it: `Behat\MinkExtension\Context\RawMinkContext` when a Mink session is all it touches, and `DrevOps\BehatSteps\Behat\Context\WebRawContext` when it reaches a backend or the extension configuration. A trait that calls nothing outside itself carries none. A context that composes `AuthTrait` declares `\DrevOps\BehatSteps\Behat\Context\UserAwareInterface` so the initializer injects the user registry and the authenticator.

## Installation & Requirements for consuming this library
```bash
composer require --dev drevops/behat-steps:^4
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

An exception's message follows [Failure messages](CONTRIBUTING.md#failure-messages).

A trait without a Mink session is one that never calls `$this->getSession()`. The ones that assert are `Steps\Web\CommandTrait`, `Steps\Drupal\ConfigTrait`, `Steps\Drupal\DrushTrait`, `Steps\Drupal\ModuleTrait`, `Steps\Drupal\QueueTrait`, `Steps\Drupal\RedirectTrait`, `Steps\Drupal\StateTrait` and `Steps\Drupal\WatchdogTrait`. Do not add a session to a trait just to reach `ExpectationException`.

In `@test-trait:` scenarios, `Then it should fail with an error:` asserts an assertion exception and `Then it should fail with an exception:` asserts a `\RuntimeException`. Use `Then it should fail with a "<class>" exception:` only when the specific class matters.

## Code Style Conventions
- Code is written using Drupal coding standards
- Local variables and method arguments: `snake_case`
- Method names and class properties: `camelCase`
- The rest is [Settled style questions](CONTRIBUTING.md#settled-style-questions)

## Documentation

[docs.php](docs.php) generates [STEPS.md](STEPS.md), the list of every step, from the traits' step attributes and docblock comments. Run `ahoy update-docs` after adding or changing a step, and `ahoy lint-docs` to check the result. [Validating and updating documentation](CONTRIBUTING.md#validating-and-updating-documentation) lists everything else it generates and validates.

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
- Consolidate related tests into existing feature files rather than creating new ones
- Use descriptive tags (e.g., `@datetime`) to allow selective test execution
- Negative tests using `@test-trait:FieldTrait` should use simple navigation (e.g., `I go to "node/add/page"`)
- Avoid using custom steps in negative tests that may not be available in BehatCLI context
- Test-only tags - ones consumed by the test harness (the test contexts or the bootstrap traits) to configure a scenario, as opposed to the library's public tags registered in `docs.php`'s `tag_registry()` - must be prefixed with `test-` (e.g., `@test-bigpipe-timeout`) so they are clearly distinguishable from real library tags.

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
    Then an email should have been sent to the address "test@example.com"
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

### Code Quality
- Run `ahoy lint` to ensure code passes all quality checks; [Lint](CONTRIBUTING.md#lint) lists what it runs

**IMPORTANT: Never Modify Code Quality Tool Configurations**
- **NEVER** modify `phpstan.neon`, `phpcs.xml`, `rector.php`, or other code quality tool configs to make lint pass
- If linting errors occur, fix the actual code to address the issues
- Do not add ignores or suppressions to configuration files
- If errors seem legitimate and can't be fixed, ask for guidance instead
- Code quality standards must be maintained consistently across the project
