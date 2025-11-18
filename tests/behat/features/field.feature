Feature: Check that FieldTrait works
  As Behat Steps library developer
  I want to provide tools to verify form field existence, state, values, and select options
  So that users can test form interactions reliably

  Scenario: Assert that a field is empty
    When I visit "/sites/default/files/fields.html"
    Then the field "field1" should be empty

  Scenario: Assert that a field is not empty
    When I visit "/sites/default/files/fields.html"
    And I fill in "field1" with "Test value"
    Then the field "field1" should not be empty

  Scenario: Assert that a field with "0" is not empty
    When I visit "/sites/default/files/fields.html"
    And I fill in "field1" with "0"
    Then the field "field1" should not be empty

  @trait:FieldTrait
  Scenario: Assert negative "the :field field should be empty" for field with "0"
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      And I fill in "field1" with "0"
      Then the field "field1" should be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The field "field1" is not empty, but should be.
      """

  @trait:FieldTrait
  Scenario: Assert negative "the :field field should be empty" for non-empty field
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      And I fill in "field1" with "Some text"
      Then the field "field1" should be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The field "field1" is not empty, but should be.
      """

  @trait:FieldTrait
  Scenario: Assert negative "the :field field should not be empty" for empty field
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      Then the field "field1" should not be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The field "field1" is empty, but should not be.
      """

  Scenario: Assert field exists
    When I visit "/sites/default/files/fields.html"
    Then the field "field1" should exist
    And the field "Field 1" should exist

  Scenario: Assert field does not exist
    When I visit "/sites/default/files/fields.html"
    Then the field "some_random_field" should not exist

  Scenario Outline: Assert field existence
    When I visit "/sites/default/files/fields.html"
    Then the field "<field>" should <existence>
    Examples:
      | field        | existence |
      | field1       | exist     |
      | Field 1      | exist     |
      | field2       | exist     |
      | Field 2      | exist     |
      | random_field | not exist |

  Scenario Outline: Assert if field is disabled or enabled
    When I visit "/sites/default/files/fields.html"
    Then the field "<field>" should have "<enabled_or_disabled>" state
    Examples:
      | field          | enabled_or_disabled |
      | field1         | enabled             |
      | Field 1        | enabled             |
      | field2         | enabled             |
      | Field 2        | enabled             |
      | field3disabled | disabled            |
      | Field 3        | disabled            |

  @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value
    When I visit "/sites/default/files/fields.html"
    Then the color field "#edit-color-input" should have the value "#000000"
    When I fill in the color field "#edit-color-input" with the value "#ffffff"
    Then the color field "#edit-color-input" should have the value "#ffffff"

  @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value using an alternate step definition
    When I visit "/sites/default/files/fields.html"
    Then the color field "#edit-color-input" should have the value "#000000"
    When I fill in the color field "#edit-color-input" with the value "#ffffff"
    Then the color field "#edit-color-input" should have the value "#ffffff"

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :field should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      Then the field "No existing field" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Form field with id|name|label|value "No existing field" not found.
      """

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :name should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      Then the field "Field 1" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "Field 1" appears on this page, but it should not.
      """

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :field should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      Then the field "field1" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field1" appears on this page, but it should not.
      """

  @trait:FieldTrait
  Scenario: Assert that "the field :field should have enabled state" fails when it is disabled
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      Then the field "field3disabled" should have "enabled" state
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field3disabled" should not be disabled, but it is.
      """

  @trait:FieldTrait
  Scenario: Assert that "the field :field should have disabled state" fails when it is not disabled
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      Then the field "field1" should have "disabled" state
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field1" should be disabled, but it is not.
      """

  @api
  Scenario: Assert "When I fill in WYSIWYG "field" with "value"" works as expected
    Given page content:
      | title             |
      | [TEST] Page title |
    And I am logged in as a user with the "administrator" role
    And I visit the "page" content edit page with the title "[TEST] Page title"
    When I fill in the WYSIWYG field "Body" with the "[TEST] body"
    And I fill in the WYSIWYG field "Description" with the "[TEST] description"
    And I press "Save"
    Then I should see "[TEST] body"
    And I should see "[TEST] description"

  @api @javascript
  Scenario: Assert "When I fill in WYSIWYG "field" with "value"" works as expected with JS driver
    Given page content:
      | title                       |
      | [TEST-JS-Driver] Page title |
    And I am logged in as a user with the "administrator" role
    And I visit the "page" content edit page with the title "[TEST-JS-Driver] Page title"
    When I fill in the WYSIWYG field "Body" with the "[TEST-JS-Driver] body"
    And I fill in the WYSIWYG field "Description" with the "[TEST-JS-Driver] description"
    And I press "Save"
    Then I should see "[TEST-JS-Driver] body"
    And I should see "[TEST-JS-Driver] description"

  @api
  Scenario: Assert that a select has/has not an option
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/regional/settings"
    Then the option "AU" should exist within the select element "site_default_country"
    And the option "DUMMY-COUNTRY" should not exist within the select element "site_default_country"

  @api
  Scenario: Assert that a select option is selected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/regional/settings"
    Then the option "UTC" should exist within the select element "date_default_timezone"
    And the option "UTC" should be selected within the select element "date_default_timezone"

  @api
  Scenario: Assert that a select option is not selected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/regional/settings"
    Then the option "Australia/Sydney" should exist within the select element "date_default_timezone"
    And the option "Australia/Sydney" should not be selected within the select element "date_default_timezone"

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should exist within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should exist within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Element "non_existent_select" is not found.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should exist within the select element :selector" for non-existent option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "INVALID_OPTION" should exist within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Option "INVALID_OPTION" is not found in select "date_default_timezone".
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should not exist within the select element :selector" for existing option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not exist within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Option "UTC" is found in select "date_default_timezone", but should not.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should be selected within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should be selected within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The select "non_existent_select" was not found on the page /admin/config/regional/settings.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should not be selected within the select element :selector" for non-existent option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "INVALID_OPTION" should not be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The option "INVALID_OPTION" was not found in the select "date_default_timezone" on the page /admin/config/regional/settings.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should not be selected within the select element :selector" for selected option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The option "UTC" was selected in the select "date_default_timezone" on the page /admin/config/regional/settings, but should not be.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should not exist within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not exist within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Element "non_existent_select" is not found.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should be selected within the select element :selector" for non-existent option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "INVALID_OPTION" should be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      No option is selected in the date_default_timezone select on the page /admin/config/regional/settings.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should be selected within the select element :selector" for non-selected option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "Australia/Sydney" should be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The option "Australia/Sydney" was not selected on the page /admin/config/regional/settings.
      """

  @api @trait:FieldTrait
  Scenario: Assert negative "the option :option should not be selected within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not be selected within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The select "non_existent_select" was not found on the page /admin/config/regional/settings.
      """

  @phpserver
  Scenario: Assert that checkboxes are checked and unchecked
    Given I am on the phpserver test page
    Then the field "Checkbox unchecked" should exist
    And the field "Checkbox checked" should exist

    And the checkbox "Checkbox unchecked" should not be checked
    And the checkbox "Checkbox checked" should be checked

    When I check the checkbox "Checkbox unchecked"
    Then the checkbox "Checkbox unchecked" should be checked

    When I uncheck the checkbox "Checkbox unchecked"
    Then the checkbox "Checkbox unchecked" should not be checked

    When I check the checkbox "Checkbox checked"
    Then the checkbox "Checkbox checked" should be checked

  @javascript
  Scenario: Disable browser validation for form after visiting page
    When I visit "/sites/default/files/fields.html"
    And browser validation for the form "#login-form" is disabled
    And I press "Submit 1"
    # Server-side validation message should appear
    Then I should see "Please fill in all required fields"

  @javascript
  Scenario: Disable browser validation as the VERY FIRST step (fixes issue #423)
    # This is the VERY FIRST step - no page visited yet - this is the core issue being fixed
    Given browser validation for the form "#login-form" is disabled
    When I visit "/sites/default/files/fields.html"
    And I press "Submit 1"
    # Server-side validation message should appear (browser validation was disabled)
    Then I should see "Please fill in all required fields"

  @javascript
  Scenario: Disable browser validation for multiple forms
    Given browser validation for the form "#login-form" is disabled
    And browser validation for the form "#contact-form" is disabled
    When I visit "/sites/default/files/fields.html"
    And I press "Submit 1"
    Then I should see "Please fill in all required fields"

  @javascript @behat-steps-skip:FieldTrait
  Scenario: Skip FieldTrait hooks with behat-steps-skip tag
    Given browser validation for the form "#login-form" is disabled
    When I visit "/sites/default/files/fields.html"
    And I press "Submit 1"
    # With the skip tag, validation disabling should not be applied
    # Browser validation will catch the empty fields before form submission
    Then I should not see "Please fill in all required fields"

  @trait:FieldTrait
  Scenario: Negative test for scenario-level behat-steps-skip tag
    Given some behat configuration
    And scenario steps tagged with "@javascript @behat-steps-skip:FieldTrait":
      """
      Given browser validation for the form "#login-form" is disabled
      When I visit "/sites/default/files/fields.html"
      And I press "Submit 1"
      Then I should not see "Please fill in all required fields"
      """
    When I run "behat --no-colors"
    Then it should pass

  Scenario: Validation step works without JavaScript driver
    Given browser validation for the form "#login-form" is disabled
    When I visit "/sites/default/files/fields.html"
    # Without JavaScript, the registry stores the selector but AfterStep returns early
    # The step should not throw an error
    Then the field "username" should exist

  @javascript @disable-form-validation
  Scenario: Tag disables all forms on page automatically
    When I visit "/sites/default/files/fields.html"
    # Try to submit login form without filling fields
    And I press "Submit 1"
    Then I should see "Please fill in all required fields"
    # Try to submit contact form without filling fields
    When I press "Submit 2"
    Then I should see "Please fill in all required fields"

  @javascript @disable-form-validation
  Scenario: Tag validation disabled across page navigation
    When I visit "/sites/default/files/form1.html"
    And I press "Submit 1"
    Then I should see "Please fill in all required fields"
    # Navigate to second page
    When I follow "Go to Second Page"
    And I press "Submit"
    # Validation should still be disabled on the second page
    Then I should see "Please fill in all required fields"

  @javascript @disable-form-validation
  Scenario: Tag works before visiting any page
    # No page visited yet - tag should still work when we visit pages
    When I visit "/sites/default/files/fields.html"
    And I press "Submit 1"
    Then I should see "Please fill in all required fields"

  @javascript
  Scenario: Without tag browser validation blocks submission
    When I visit "/sites/default/files/fields.html"
    And I press "Submit 1"
    # Browser validation will block, so we won't see the error message
    Then I should not see "Please fill in all required fields"

  @javascript
  Scenario: Selector-based approach still works independently
    Given browser validation for the form "#login-form" is disabled
    When I visit "/sites/default/files/fields.html"
    And I press "Submit 1"
    Then I should see "Login form error: Please fill in all required fields"
    # Contact form should still have browser validation
    When I press "Submit 2"
    Then I should not see "Contact form error: Please fill in all required fields"

  @javascript @disable-form-validation @behat-steps-skip:FieldTrait
  Scenario: Skip tag overrides disable-form-validation tag
    When I visit "/sites/default/files/fields.html"
    And I press "Submit 1"
    # With skip tag, validation disabling should not be applied
    Then I should not see "Please fill in all required fields"

  @disable-form-validation
  Scenario: Tag works gracefully without JavaScript driver
    When I visit "/sites/default/files/fields.html"
    # Without JavaScript, the tag should not throw an error
    Then the field "username" should exist

  @api @datetime
  Scenario: Fill datetime field with date and time
    Given page content:
      | title                     |
      | [TEST] Datetime test page |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Datetime test page"
    And I fill in the datetime field "Event date" with date "2024-01-15" and time "14:30:00"
    And I press "Save"
    Then I should see the text "Page [TEST] Datetime test page has been updated."

  @api @datetime
  Scenario: Fill datetime field using separate date and time steps
    Given page content:
      | title                          |
      | [TEST] Datetime separate steps |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Datetime separate steps"
    And I fill in the date part of the datetime field "Event date" with "2024-02-20"
    And I fill in the time part of the datetime field "Event date" with "15:45:00"
    And I press "Save"
    Then I should see the text "Page [TEST] Datetime separate steps has been updated."

  @api @datetime
  Scenario: Fill date-only field
    Given page content:
      | title                      |
      | [TEST] Date only test page |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Date only test page"
    And I fill in the datetime field "Event date only" with date "2024-03-10" and time ""
    And I press "Save"
    Then I should see the text "Page [TEST] Date only test page has been updated."

  @api @datetime
  Scenario: Fill date-only field using date part step
    Given page content:
      | title                      |
      | [TEST] Date part test page |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Date part test page"
    And I fill in the date part of the datetime field "Event date only" with "2024-04-05"
    And I press "Save"
    Then I should see the text "Page [TEST] Date part test page has been updated."

  @api @datetime
  Scenario: Fill daterange field with start and end dates
    Given page content:
      | title                      |
      | [TEST] Daterange test page |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Daterange test page"
    And I fill in the start datetime field "Event period" with date "2024-06-01" and time "09:00:00"
    And I fill in the end datetime field "Event period" with date "2024-06-05" and time "17:00:00"
    And I press "Save"
    Then I should see the text "Page [TEST] Daterange test page has been updated."

  @api @datetime
  Scenario: Fill daterange date-only field
    Given page content:
      | title                                |
      | [TEST] Daterange date only test page |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Daterange date only test page"
    And I fill in the start datetime field "Event period date only" with date "2024-07-10" and time ""
    And I fill in the end datetime field "Event period date only" with date "2024-07-15" and time ""
    And I press "Save"
    Then I should see the text "Page [TEST] Daterange date only test page has been updated."

  @trait:FieldTrait @datetime
  Scenario: Assert negative "fill in the datetime field" for non-existent field
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      And I go to "node/add/page"
      And I fill in the datetime field "Non-existent field" with date "2024-01-01" and time "12:00:00"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Datetime field "Non-existent field" with part "value" and field "date" not found.
      """

  @trait:FieldTrait @datetime
  Scenario: Assert negative "fill in the date part of the datetime field" for non-existent field
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      And I go to "node/add/page"
      And I fill in the date part of the datetime field "Non-existent field" with "2024-01-01"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Datetime field "Non-existent field" with part "value" and field "date" not found.
      """

  @trait:FieldTrait @datetime
  Scenario: Assert negative "fill in the time part of the datetime field" for non-existent field
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      And I go to "node/add/page"
      And I fill in the time part of the datetime field "Non-existent field" with "12:00:00"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Datetime field "Non-existent field" with part "value" and field "time" not found.
      """

  @trait:FieldTrait @datetime
  Scenario: Assert negative "fill in the start datetime field" for non-existent field
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      And I go to "node/add/page"
      And I fill in the start datetime field "Non-existent range" with date "2024-01-01" and time ""
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Datetime field "Non-existent range" with part "value" and field "date" not found.
      """

  @trait:FieldTrait @datetime
  Scenario: Assert negative "fill in the end datetime field" for non-existent field
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      And I go to "node/add/page"
      And I fill in the end datetime field "Non-existent range" with date "2024-01-05" and time ""
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Datetime field "Non-existent range" with part "end_value" and field "date" not found.
      """

  @trait:FieldTrait
  Scenario: Assert negative color field value assertion when values don't match
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I visit "/sites/default/files/fields.html"
      Then the color field "#edit-color-input" should have the value "#000000"
      When I fill in the color field "#edit-color-input" with the value "#ffffff"
      Then the color field "#edit-color-input" should have the value "#000000"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Color field "#edit-color-input" expected a value "#000000" but has a value "#ffffff".
      """

  @api @javascript
  Scenario: Fill in WYSIWYG field with CKEditor 5
    When I visit "/sites/default/files/wysiwyg_ckeditor5.html"
    And I fill in the WYSIWYG field "Body" with the "Updated CKEditor 5 body content"
    And I fill in the WYSIWYG field "Description" with the "Updated CKEditor 5 description"

  # Non-commercial version of CKEditor 4 throw an error about being insecure.
  @api @javascript @js-errors
  Scenario: Fill in WYSIWYG field with CKEditor 4
    When I visit "/sites/default/files/wysiwyg_ckeditor4.html"
    And I fill in the WYSIWYG field "Body" with the "Updated CKEditor 4 body content"
    And I fill in the WYSIWYG field "Description" with the "Updated CKEditor 4 description"

  @trait:FieldTrait
  Scenario: Assert negative WYSIWYG field not found
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I visit "/sites/default/files/wysiwyg_ckeditor5.html"
      When I fill in the WYSIWYG field "Non-existent WYSIWYG" with the "test content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Form field with id|name|label|value|placeholder "Non-existent WYSIWYG" not found.
      """

  @trait:FieldTrait
  Scenario: Assert negative WYSIWYG field without an ID
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I visit "/sites/default/files/wysiwyg_ckeditor5.html"
      When I fill in the WYSIWYG field "noid" with the "test content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      WYSIWYG field must have an ID attribute.
      """

  @select
  Scenario: Unselect option from multi-select field
    When I visit "/sites/default/files/fields.html"
    And I additionally select "Option A" from "Multi-select options"
    And I additionally select "Option B" from "Multi-select options"
    And I additionally select "Option C" from "Multi-select options"
    Then the option "Option A" should be selected within the select element "Multi-select options"
    And the option "Option B" should be selected within the select element "Multi-select options"
    And the option "Option C" should be selected within the select element "Multi-select options"
    When I unselect "Option B" from "Multi-select options"
    Then the option "Option A" should be selected within the select element "Multi-select options"
    And the option "Option B" should not be selected within the select element "Multi-select options"
    And the option "Option C" should be selected within the select element "Multi-select options"

  @select
  Scenario: Clear all selections from multi-select field
    When I visit "/sites/default/files/fields.html"
    And I additionally select "Option A" from "Multi-select options"
    And I additionally select "Option B" from "Multi-select options"
    Then the option "Option A" should be selected within the select element "Multi-select options"
    And the option "Option B" should be selected within the select element "Multi-select options"
    When I clear the select "Multi-select options"
    Then the option "Option A" should not be selected within the select element "Multi-select options"
    And the option "Option B" should not be selected within the select element "Multi-select options"
    And the option "Option C" should not be selected within the select element "Multi-select options"

  @select
  Scenario: Clear single select field
    When I visit "/sites/default/files/fields.html"
    And I select "Choice 1" from "Single select field"
    Then the option "Choice 1" should be selected within the select element "Single select field"
    When I clear the select "Single select field"
    Then the option "Choice 1" should not be selected within the select element "Single select field"

  @select
  Scenario: Unselect option from single select field
    When I visit "/sites/default/files/fields.html"
    And I select "Choice 2" from "Single select field"
    Then the option "Choice 2" should be selected within the select element "Single select field"
    When I unselect "Choice 2" from "Single select field"
    Then the option "Choice 2" should not be selected within the select element "Single select field"

  @trait:FieldTrait
  Scenario: Assert negative "When I unselect :option from :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      When I unselect "Option A" from "Non-existent select"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The select "Non-existent select" was not found.
      """

  @trait:FieldTrait
  Scenario: Assert negative "When I unselect :option from :selector" for non-existent option
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      When I unselect "Invalid Option" from "Multi-select options"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The option "Invalid Option" was not found in the select "Multi-select options".
      """

  @trait:FieldTrait
  Scenario: Assert negative "When I clear the select :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/fields.html"
      When I clear the select "Non-existent select"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The select "Non-existent select" was not found.
      """
