Feature: Check that TableTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTML table content and structure
  So that users can test tabular data reliably

  @api
  Scenario: Assert "Then the table :selector should have :count row(s)" works as expected
    Given I am logged in as a user with the "administrator" role
    And page content:
      | title                |
      | [TEST] Table page 1  |
      | [TEST] Table page 2  |
      | [TEST] Table page 3  |
    When I visit "/admin/content"
    Then the table ".views-table" should have 3 rows

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count row(s)" fails when table not found
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      Then the table ".nonexistent-table" should have 1 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Table with selector ".nonexistent-table" not found.
      """

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count row(s)" fails when row count does not match
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      And page content:
        | title               |
        | [TEST] Table page 1 |
      When I visit "/admin/content"
      Then the table ".views-table" should have 99 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".views-table" to have 99 row(s), but found
      """

  @api
  Scenario: Assert "Then the table :selector should contain the following columns:" works as expected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/people"
    Then the table ".views-table" should contain the following columns:
      | Username |
      | Status   |
      | Roles    |

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should contain the following columns:" fails when table not found
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/people"
      Then the table ".nonexistent-table" should contain the following columns:
        | Username |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Table with selector ".nonexistent-table" not found.
      """

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should contain the following columns:" fails when column not found
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/people"
      Then the table ".views-table" should contain the following columns:
        | NonExistentColumn |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Column "NonExistentColumn" not found in table ".views-table".
      """

  @api
  Scenario: Assert "Then the :rowText row should contain the following:" works as expected
    Given I am logged in as a user with the "administrator" role
    And page content:
      | title                    |
      | [TEST] Findable content  |
    When I visit "/admin/content"
    Then the "[TEST] Findable content" row should contain the following:
      | [TEST] Findable content |
      | Page                    |

  @trait:TableTrait
  Scenario: Assert "Then the :rowText row should contain the following:" fails when row not found
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      Then the "NonExistentRowText" row should contain the following:
        | some text |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Table row containing text "NonExistentRowText" not found.
      """

  @trait:TableTrait
  Scenario: Assert "Then the :rowText row should contain the following:" fails when text not found in row
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      And page content:
        | title                   |
        | [TEST] Findable content |
      When I visit "/admin/content"
      Then the "[TEST] Findable content" row should contain the following:
        | NonExistentCellText |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Row containing "[TEST] Findable content" does not contain expected text "NonExistentCellText".
      """
