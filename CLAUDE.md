# DrevOps Behat Steps

## Build/Lint/Test Commands
- Build/provision: `ahoy build`, `ahoy up`, `ahoy provision`
- Lint: `ahoy lint`, `ahoy lint-fix`
- Tests: `ahoy test-bdd`, `ahoy test-bdd path/to/file`, `ahoy test-bdd -- --tags=wip`
- Debug: `ahoy debug` (enables Xdebug)

## Code Style Guidelines
- PHP version: 8.2+
- Strict typing required: `declare(strict_types=1);`
- Types: Full type declarations (parameters, return values, PHPDoc)
- Array syntax: Use generics for arrays `array<int, string>`
- Exceptions: `\Exception` for assertions, `\RuntimeException` for unfulfilled requirements
- PHPDoc: Required for all methods with complete parameter and return types
- Follows Drupal coding standards with PHPStan level 7

## Project Structure
- PHP Traits in `src/` directory, each focused on specific Drupal functionality
- Tests in `tests/behat/features/` directory with matching feature files
- Step traits are imported into a FeatureContext.php class
- Designed for Drupal 10 testing with Behat

## Writing Feature Files

### Step Definition Analysis
- Inspect trait files for `@Given`, `@When`, `@Then` annotations to identify available steps
- Analyze parameter patterns (`:variable`) that show where values are injected
- Read docblocks and `@code` examples for usage guidance
- Check method implementations to understand how parameters are processed

### Feature File Structure
- Use feature name format: `Check that [TraitName] works`
- Add `@api` tag for Drupal API interaction, `@javascript` for JS tests
- Tag with `@trait:[TraitName]` for error case scenarios
- Start scenarios with clear descriptions of what's being tested
- Follow logical progression: setup → action → verification

### Step Writing Guidelines
- **Given** steps establish test state (content creation, user login)
- **When** steps perform actions (page visits, configuration)
- **Then** steps verify results (assertions about content/state)
- Use tables for structured data input (entities, field values)
- Add `[TEST]` prefix to test content names for easy identification

### Testing Strategy
- Create positive scenarios that verify features work correctly
- Include negative scenarios to verify proper error handling
- Test edge cases and different configuration options
- Create complete workflows (create → edit → verify → delete)
- Test both creation and removal of entities
- Verify specific error messages in exception cases

### Authentication Pattern
- Use `Given I am logged in as a user with the "administrator" role` for admin actions
- Test features with different user permissions when relevant

### Cleanup & Organization
- Ensure tests clean up created test content
- Keep scenarios focused on testing a single aspect
- Group related scenarios in the same feature file
- Follow existing test patterns for consistency

### Common Page Assertions
1. **`Then I should see "{text}"`** - Verifies text is visible on the page
2. **`Then I should see the "{selector}" element with the "{attribute}" attribute set to "{value}"`** - Confirms element has specific attribute value
3. **`Then I should see a visible "{selector}" element`** - Checks element exists and is visible
4. **`Then I should see the "{selector}" element with a(n) "{attribute}" attribute containing "{value}"`** - Verifies partial attribute value
5. **`Then I should see an element "{selector}" using "{selector_type}" contains "{text}" text`** - Checks text within specific element (CSS/XPath)
6. **`Then I should not see a visible "{selector}" element`** - Verifies element is not visible
7. **`Then I should see a visually visible "{selector}" element`** - Confirms element is visually displayed (not hidden by CSS)
8. **`Then I should see "{text}" in the "{selector}" element`** - Checks text exists within a specific element
9. **`Then I should not see "{text}"`** - Verifies text does not appear on the page
10. **`Then I should not see a visually hidden "{selector}" element`** - Checks element is not hidden by CSS techniques

## Block and BlockContent Traits

### BlockTrait Steps
- **When I configure the block with the label :label with:**
  - Configure block settings (label, region, visibility, status)
- **When I configure the visibility condition :condition for the block with label :label**
  - Set visibility conditions like path, role, etc.
- **When I remove the visibility condition :condition from the block with label :label**
  - Remove visibility restrictions
- **When I disable the block with label :label**
  - Disable a block
- **When I enable the block with label :label**
  - Enable a block
- **Then block with label :label should exist**
  - Verify block has been created
- **Then block with label :label should exist in the region :region**
  - Verify block placement
- **Then block with label :label should not exist in the region :region**
  - Verify block is not in specified region
- **Then the block with label :label should have the visibility condition :condition**
  - Verify visibility settings
- **Then the block with label :label is disabled**
  - Verify block state
- **Then the block with label :label is enabled**
  - Verify block state

### BlockContentTrait Steps
- **Given block_content_type :type with description :description exists**
  - Verify block content type exists
- **Given block_content :name of block_content_type :type exists**
  - Verify specific block content exists
- **Given no :type block_content:**
  - Remove specified block content entities
- **Given :type block_content:**
  - Create block content entities (custom blocks)
- **When I edit :type block_content_type with description :info**
  - Navigate to edit a block content