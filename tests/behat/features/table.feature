Feature: Check that TableTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTML table content and structure
  So that users can test tabular data reliably

  # Row count.

  Scenario: Assert "Then the table :selector should have :count row(s)" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-asc" should have 3 rows

  Scenario: Assert "Then the table :selector should have :count row(s)" works with single row
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-single" should have 1 row

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count row(s)" fails when table not found
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".nonexistent" should have 1 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Table with selector ".nonexistent" not found.
      """

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count row(s)" fails when row count does not match
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-asc" should have 99 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-asc" to have 99 row(s), but found 3.
      """

  # Column count.

  Scenario: Assert "Then the table :selector should have :count column(s)" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-asc" should have 3 columns

  Scenario: Assert "Then the table :selector should have :count column(s)" works with different table
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-desc" should have 2 columns

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count column(s)" fails when column count does not match
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-asc" should have 99 columns
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-asc" to have 99 column(s), but found 3.
      """

  # Column headers.

  Scenario: Assert "Then the table :selector should contain the following columns:" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-asc" should contain the following columns:
      | Name     |
      | Category |
      | Status   |

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should contain the following columns:" fails when column not found
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-asc" should contain the following columns:
        | NonExistent |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Column "NonExistent" not found in table ".table-asc".
      """

  # Empty and not empty.

  Scenario: Assert "Then the table :selector should be empty" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-empty" should be empty

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should be empty" fails when table has rows
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-asc" should be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-asc" to be empty, but found 3 row(s).
      """

  Scenario: Assert "Then the table :selector should not be empty" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-asc" should not be empty

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should not be empty" fails when table is empty
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-empty" should not be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-empty" to not be empty, but it has no rows.
      """

  # Sort order.

  Scenario: Assert "Then the table :selector should be sorted by :column in :direction order" works with ascending order
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-asc" should be sorted by "Name" in "ascending" order

  Scenario: Assert "Then the table :selector should be sorted by :column in :direction order" works with descending order
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-desc" should be sorted by "Name" in "descending" order

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should be sorted by :column in :direction order" fails with invalid direction
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-asc" should be sorted by "Name" in "invalid" order
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Invalid sort direction "invalid". Use "ascending" or "descending".
      """

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should be sorted by :column in :direction order" fails when not sorted
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-desc" should be sorted by "Name" in "ascending" order
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-desc" to be sorted by "Name" in ascending order.
      """

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should be sorted by :column in :direction order" fails when column not found
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-asc" should be sorted by "NonExistent" in "ascending" order
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Column "NonExistent" not found in table ".table-asc".
      """

  # Row content.

  Scenario: Assert "Then the table :selector should contain the following rows:" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the table ".table-asc" should contain the following rows:
      | Name       | Status   |
      | Alpha item | Active   |
      | Beta item  | Inactive |

  @trait:TableTrait
  Scenario: Assert "Then the table :selector should contain the following rows:" fails when row not found
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the table ".table-asc" should contain the following rows:
        | Name         |
        | Non Existent |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      not found in table ".table-asc".
      """

  # Row text.

  Scenario: Assert "Then the :rowText row should contain the following:" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/table.html"
    Then the "Alpha item" row should contain the following:
      | Type A  |
      | Active  |

  @trait:TableTrait
  Scenario: Assert "Then the :rowText row should contain the following:" fails when row not found
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the "NonExistent" row should contain the following:
        | some text |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Table row containing text "NonExistent" not found.
      """

  @trait:TableTrait
  Scenario: Assert "Then the :rowText row should contain the following:" fails when text not found in row
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/table.html"
      Then the "Alpha item" row should contain the following:
        | NonExistent |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Row containing "Alpha item" does not contain expected text "NonExistent".
      """
