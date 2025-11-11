# Behat Steps - Claude Memory

## Project Context
This repository contains Behat step definitions for PHP projects (with specialized support for Drupal), organized as traits that can be included in Behat contexts. The steps provide reusable testing functionality for generic PHP components and Drupal-specific features.

## Source files location

Source files are located in the `src` directory. Each trait is organized into a separate file, and the steps are defined within those files.


## Installation & Requirements for cosnuming this library
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
- `ahoy update-fixtures` - Copy configuration changes from build directory to fixtures
- `ahoy update-docs` - Update documentation
- `ahoy lint-docs` - Check documentation for errors
- `ahoy lint` - Run linting (if docker-compose.yml exists, otherwise use `composer lint`)
- `ahoy lint-fix` - Fix linting issues (if docker-compose.yml exists, otherwise use `composer lint-fix`)
- `ahoy test-unit` - Run unit tests (if docker-compose.yml exists, otherwise use `composer test`)

## Steps Format Guidelines
- **General Guidelines**:
  - Use tuple format instead of regular expressions
  - Use descriptive placeholder names
  - Use `the following` for tabled content
  - Use `with` for properties: `Then the link with the title :title should exist`
  - Avoid optional words like `(the|a)`
  - Omit unnecessary suffixes like `on the page`
  - Method names should begin with the trait name: `userAssertHasRoles()`

- **Given Steps**:
  - Define test prerequisites
  - Use words like `exists` or `have`
  - Avoid using `should` or `should not`
  - Avoid using `Given I`

- **When Steps**:
  - Describe an action with an action verb
  - Use the format `When I <verb>`

- **Then Steps**:
  - Specify assertions and expectations
  - Use `should` and `should not` for assertions
  - Start with the entity being asserted
  - Avoid using `Then I`
  - Methods should include the `Assert` prefix

## Common Behat Step Patterns
- Block assertions:
  - `I should see the block with label "..."`
  - `I should see the block with label "..." in the region "..."`

- Content block operations:
  - `the content block type "..." should exist`
  - `the following "..." content blocks exist:`
  - `I edit the "..." content block with the description "..."`

- Email testing:
  - `I enable the test email system`
  - `I clear the test email system queue`
  - `an email should be sent to the "..."`

## Skipping Before Scenario Hooks
Some traits provide `beforeScenario` hook implementations that can be disabled by adding `behat-steps-skip:METHOD_NAME` tag to your test.

Example: To skip `beforeScenario` hook from `ElementTrait`, add `@behat-steps-skip:ElementTrait` tag to the feature.

## Code Style Conventions
- Code is written using Drupal coding standards
- Local variables and method arguments: `snake_case`
- Method names and class properties: `camelCase`

## Documentation
- List of all available steps is produced from trait and method comments and exported into [STEPS.md](STEPS.md)
- Migration guide for upgrading from 2.x: [MIGRATION.md](MIGRATION.md)

### Updating Steps Documentation
The [STEPS.md](STEPS.md) documentation is automatically generated from the source code using the [docs.php](docs.php) file. After making changes to step definitions or adding new ones, you should regenerate the documentation:

1. Using Ahoy (recommended):
   ```bash
   ahoy update-docs
   ```

2. Direct PHP execution:
   ```bash
   php docs.php > STEPS.md
   ```

3. Linting the documentation:
   ```bash
   ahoy lint-docs
   ```

This ensures that the documentation remains in sync with the actual code implementation.

## Implementation Patterns and Learnings

### Working with Drupal Compound Fields
- Drupal compound fields (like datetime, daterange) have multiple sub-inputs that cannot be targeted with standard `findField()`
- Use XPath-based selectors to locate specific sub-inputs within compound fields
- Implement fallback strategies: try label elements first, then span elements, then generic class-based searches
- Compound field structure example: `field_name[0][value][date]` and `field_name[0][value][time]`
- Date range fields use `[value]` for start and `[end_value]` for end components

### Field Configuration Management
- New field configurations must be added to **both** d10 and d11 fixtures
- Field configs include: field storage, field instance, and form display updates
- When adding fields, update `core.entity_form_display.node.page.default.yml` with:
  - Field references in dependencies config section
  - Module dependencies (e.g., `datetime`, `datetime_range`)
  - Widget configuration with type, weight, region, and settings
- After creating configs in `build/config/sync`, copy to both fixture directories
- Use `ahoy drush cim -y` to import configurations into the build environment

### Test Organization and Tagging
- Consolidate related tests into existing feature files rather than creating new ones
- Use descriptive tags (e.g., `@datetime`) to allow selective test execution
- Negative tests using `@trait:FieldTrait` should use simple navigation (e.g., `I go to "node/add/page"`)
- Avoid using custom steps in negative tests that may not be available in BehatCLI context

### Step Definition Constraints
- Documentation tool (`docs.php`) does not support multiple `@When` annotations per method
- Use a single step annotation and document alternative usage in `@code` examples
- Optional parameters should use empty string defaults, not PHP optional parameters
- Always provide both imperative (content) and continuous (activeForm) task descriptions

### Field Naming Conventions
- Use descriptive field names without "test" prefix (e.g., `field_datetime` not `field_test_datetime`)
- Field labels should be user-friendly: "Event date", "Event period", etc.
- Machine names follow Drupal conventions: `field_{description}`

### Documentation and Code Quality
- Always run `ahoy update-docs` after adding/modifying step definitions
- Use `ahoy lint-docs` to verify documentation format
- Run `ahoy lint` to ensure code passes all quality checks (PHPStan, Rector, Gherkinlint)
- Run BDD tests with specific tags during development: `ahoy test-bdd -- --tags="@tagname"`
