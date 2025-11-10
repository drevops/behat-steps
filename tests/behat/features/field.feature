Feature: Check that FieldTrait works
  As Behat Steps library developer
  I want to provide tools to verify form field existence, state, values, and select options
  So that users can test form interactions reliably

  @api
  Scenario: Assert that a field is empty
    When I go to "form/test-form"
    Then the field "field1" should be empty

  @api
  Scenario: Assert that a field is not empty
    When I go to "form/test-form"
    And I fill in "field1" with "Test value"
    Then the field "field1" should not be empty

  @api
  Scenario: Assert that a field with "0" is not empty
    When I go to "form/test-form"
    And I fill in "field1" with "0"
    Then the field "field1" should not be empty

  @trait:FieldTrait
  Scenario: Assert negative "the :field field should be empty" for field with "0"
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
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
      Given I go to "form/test-form"
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
      Given I go to "form/test-form"
      Then the field "field1" should not be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The field "field1" is empty, but should not be.
      """

  @api
  Scenario: Assert field exists
    When I go to "form/test-form"
    Then the field "field1" should exist
    And the field "Field 1" should exist

  @api
  Scenario: Assert field does not exist
    When I go to "test-form"
    Then the field "some_random_field" should not exist

  @api
  Scenario Outline: Assert field existence
    When I go to "form/test-form"
    Then the field "<field>" should <existence>
    Examples:
      | field        | existence |
      | field1       | exist     |
      | Field 1      | exist     |
      | field2       | exist     |
      | Field 2      | exist     |
      | random_field | not exist |

  @api
  Scenario Outline: Assert if field is disabled or enabled
    When I go to "form/test-form"
    Then the field "<field>" should have "<enabled_or_disabled>" state
    Examples:
      | field          | enabled_or_disabled |
      | field1         | enabled             |
      | Field 1        | enabled             |
      | field2         | enabled             |
      | Field 2        | enabled             |
      | field3disabled | disabled            |
      | Field 3        | disabled            |

  @api @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value
    When I visit "/sites/default/files/relative.html"
    Then the color field "#edit-color-input" should have the value "#000000"
    When I fill in the color field "#edit-color-input" with the value "#ffffff"
    Then the color field "#edit-color-input" should have the value "#ffffff"

  @api @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value using an alternate step definition
    When I visit "/sites/default/files/relative.html"
    Then the color field "#edit-color-input" should have the value "#000000"
    When I fill in the color field "#edit-color-input" with the value "#ffffff"
    Then the color field "#edit-color-input" should have the value "#ffffff"

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :field should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
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
      Given I go to "form/test-form"
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
      Given I go to "form/test-form"
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
      Given I go to "form/test-form"
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
      Given I go to "form/test-form"
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

    When I check the checkbox "Checkbox checked"
    Then the checkbox "Checkbox checked" should be checked

  @api @javascript @validation
  Scenario: Disable browser validation for article creation form
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And browser validation for the form "#node-article-form" is disabled
     # Try to submit without filling in required fields.
    And I press "Save"
     # Server-side validation errors should appear.
    Then I should see "Title field is required"

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
      | title                        |
      | [TEST] Date part test page   |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Date part test page"
    And I fill in the date part of the datetime field "Event date only" with "2024-04-05"
    And I press "Save"
    Then I should see the text "Page [TEST] Date part test page has been updated."

  @api @datetime
  Scenario: Fill daterange field with start and end dates
    Given page content:
      | title                        |
      | [TEST] Daterange test page   |
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
