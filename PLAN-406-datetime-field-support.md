# Implementation Plan for DateTime Field Handling (Issue #406)

**Issue:** https://github.com/drevops/behat-steps/issues/406

Based on analysis of the codebase and the referenced implementation from Metadrop, here's the comprehensive plan:

## Overview
Add datetime field handling to support setting date, time, and datetime fields in Drupal forms, following the project's existing patterns and conventions.

## Implementation Details

### 1. **Add datetime field handling methods to `FieldTrait.php`**

**New methods to add:**

- `fieldFillDatetime(string $label, string $date, string $time = ''): void`
  - Step: `When I fill in the datetime field :label with date :date and time :time`
  - Step: `When I fill in the datetime field :label with date :date`
  - Fills both date and optionally time components of a datetime field

- `fieldFillDatetimeDate(string $label, string $date): void`
  - Step: `When I fill in the date part of the datetime field :label with :date`
  - Fills only the date component

- `fieldFillDatetimeTime(string $label, string $time): void`
  - Step: `When I fill in the time part of the datetime field :label with :time`
  - Fills only the time component

- `fieldFillDatetimeStart(string $label, string $date, string $time = ''): void`
  - Step: `When I fill in the start datetime field :label with date :date and time :time`
  - Handles "start" value for date range fields

- `fieldFillDatetimeEnd(string $label, string $date, string $time = ''): void`
  - Step: `When I fill in the end datetime field :label with date :date and time :time`
  - Handles "end_value" for date range fields

**Helper method:**
- `fieldFillDatetimeHelper(string $label, string $part, string $field, string $value): void`
  - Core implementation that uses XPath to locate datetime field inputs
  - Parameters:
    - `$label`: Field label text
    - `$part`: 'value' (start) or 'end_value' (end)
    - `$field`: 'date' or 'time'
    - `$value`: The value to set

**XPath strategy:**
```php
// Locate datetime inputs by field label and name attribute
sprintf(
  '//label[contains(text(), "%s")]/..//input[contains(@name, "[%s][%s]")]',
  $label, $part, $field
)
```

### 2. **Create datetime field configurations**

**Four fields to create in `build/config/sync/`:**

1. **`field_datetime`**
   - Type: `datetime`
   - Storage: Single value
   - Settings: Date and time
   - Tests: Regular datetime methods with both date and time

2. **`field_date_only`**
   - Type: `datetime`
   - Storage: Single value
   - Settings: Date only (no time)
   - Tests: Regular datetime methods with date only

3. **`field_daterange`**
   - Type: `daterange`
   - Storage: Start and end values
   - Settings: Date and time
   - Tests: Start/end datetime methods with both date and time

4. **`field_daterange_date_only`**
   - Type: `daterange`
   - Storage: Start and end values
   - Settings: Date only (no time)
   - Tests: Start/end datetime methods with date only

**Configuration files to create:**
- `field.storage.node.field_datetime.yml`
- `field.field.node.page.field_datetime.yml`
- `field.storage.node.field_date_only.yml`
- `field.field.node.page.field_date_only.yml`
- `field.storage.node.field_daterange.yml`
- `field.field.node.page.field_daterange.yml`
- `field.storage.node.field_daterange_date_only.yml`
- `field.field.node.page.field_daterange_date_only.yml`
- Update `core.entity_form_display.node.page.default.yml` - Add form display configuration for all 4 fields

**Implementation steps:**
1. Create configuration files in `build/config/sync/`
2. Import configuration using `ahoy drush cim -y`
3. User will manually move configuration to `tests/behat/fixtures/d10/config/sync/` and `tests/behat/fixtures/d11/config/sync/` later

### 3. **Create Behat test scenarios**

**New feature file: `tests/behat/features/field_datetime.feature`**

**Test scenarios to include:**
- Fill datetime field with date only
- Fill datetime field with date and time
- Fill date part of datetime field
- Fill time part of datetime field
- Fill start datetime field (date range)
- Fill end datetime field (date range)
- Negative tests for non-existent fields
- Integration with DateTrait's relative date transformations (e.g., `[relative:-1 day]`)
- Both `@api` (non-JS) and `@javascript` scenarios

### 4. **Update documentation**
- Run `ahoy update-docs` to regenerate `STEPS.md` from the new method annotations
- Verify the documentation is properly formatted with `ahoy lint-docs`

### 5. **Testing and validation**
- Run `ahoy test-bdd tests/behat/features/field_datetime.feature` for the new tests
- Run full test suite with `ahoy test-bdd` to ensure no regressions
- Fix any linting issues with `ahoy lint-fix`

## Technical Considerations

1. **Consistent language:** Following project conventions:
   - Use descriptive placeholder names (`:label`, `:date`, `:time`)
   - Methods start with trait name prefix: `fieldFillDatetime*()`
   - Steps use "When I..." for actions
   - Use "the datetime field" consistently in step definitions

2. **Integration with existing traits:**
   - Works with `DateTrait` for `[relative:...]` transformations
   - Follows patterns from `FieldTrait` for field interaction
   - Uses standard Mink/Behat APIs

3. **Drupal-specific considerations:**
   - Datetime fields in Drupal use separate `date` and `time` HTML inputs
   - Field names contain `[value][date]` and `[value][time]` parts
   - Date range fields use `[value]` for start and `[end_value]` for end

4. **Error handling:**
   - Throw descriptive exceptions when fields aren't found
   - Follow existing error message patterns from other field methods

## Files to Create/Modify

**Modified:**
- `src/FieldTrait.php` (add new methods)
- `build/config/sync/core.entity_form_display.node.page.default.yml` (add form display for 4 new fields)

**Created in `build/config/sync/` (to be moved to fixtures later):**
- `field.storage.node.field_datetime.yml`
- `field.field.node.page.field_datetime.yml`
- `field.storage.node.field_date_only.yml`
- `field.field.node.page.field_date_only.yml`
- `field.storage.node.field_daterange.yml`
- `field.field.node.page.field_daterange.yml`
- `field.storage.node.field_daterange_date_only.yml`
- `field.field.node.page.field_daterange_date_only.yml`

**Created:**
- `tests/behat/features/field_datetime.feature`

**Updated (via automation):**
- `STEPS.md` (via `ahoy update-docs`)

**To be done manually later:**
- Move configuration files from `build/config/sync/` to `tests/behat/fixtures/d10/config/sync/`
- Replicate configuration files to `tests/behat/fixtures/d11/config/sync/`

## Success Criteria
- ✅ All new Behat tests pass
- ✅ Existing tests continue to pass (no regressions)
- ✅ Code linting passes
- ✅ Documentation is updated and properly formatted
- ✅ Supports date-only, time-only, and datetime fields
- ✅ Supports date range fields (start/end)
- ✅ Integrates with relative date transformation

## Notes
- Reference implementation: https://github.com/Metadrop/behat-contexts/blob/48e079eac0cc96e15b0cf0906b4e03841e47eb76/src/Behat/Context/FormContext.php#L69
- This implementation should use consistent language and follow DrevOps coding standards
- All step definitions should follow the tuple format (not regex)
