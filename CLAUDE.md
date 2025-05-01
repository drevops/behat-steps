# DrevOps Behat Steps - Claude Memory

## Project Context
This repository contains Behat step definitions for Drupal projects, organized as traits that can be included in Behat contexts. The steps provide reusable testing functionality for Drupal components and features.

## Source files location

Source files are located in the `src` directory. Each trait is organized into a separate file, and the steps are defined within those files.


## Installation & Requirements
```bash
composer require --dev drevops/behat-steps:^3
```
## Development Commands
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
- Local variables and method arguments: `snake_case`
- Method names and class properties: `camelCase`

## Documentation
- List of all available steps: [steps.md](steps.md)
- Migration guide for upgrading from 2.x: [MIGRATION.md](MIGRATION.md)

### Updating Steps Documentation
The [steps.md](steps.md) documentation is automatically generated from the source code using the [docs.php](docs.php) file. After making changes to step definitions or adding new ones, you should regenerate the documentation:

1. Using Ahoy (recommended):
   ```bash
   ahoy update-docs
   ```

2. Direct PHP execution:
   ```bash
   php docs.php > steps.md
   ```

3. Linting the documentation:
   ```bash
   ahoy lint-docs
   ```

This ensures that the documentation remains in sync with the actual code implementation.
