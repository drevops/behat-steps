Feature: Check that TableTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTML table content and structure
  So that users can test tabular data reliably

  @phpserver
  Scenario: Assert "Then the table :selector should have :count row(s)" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-asc" should have 3 rows

  @phpserver
  Scenario: Assert "Then the table :selector should have :count row(s)" works with single row
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-single" should have 1 row

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count row(s)" fails when table not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".nonexistent" should have 1 row
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ElementNotFoundException" exception:
      """
      Table matching css ".nonexistent" not found.
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count row(s)" fails when row count does not match
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-asc" should have 99 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-asc" to have 99 row(s), but found 3.
      """

  @test-trait:TableTrait
  Scenario Outline: Assert "Then the table :selector should have :count row(s)" fails when the count is not an integer of 0 or more
    Given some behat configuration
    And scenario steps:
      """
      Then the table ".table-asc" should have "<count>" rows
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      <message>
      """
    Examples:
      | count | message                                             |
      | many  | The count must be an integer, but "many" was given. |
      | 2.5   | The count must be an integer, but "2.5" was given.  |
      | -1    | The count must be 0 or greater, but "-1" was given. |

  @phpserver
  Scenario: Assert "Then the table :selector should have :count column(s)" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-asc" should have 3 columns

  @phpserver
  Scenario: Assert "Then the table :selector should have :count column(s)" works with different table
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-desc" should have 2 columns

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count column(s)" fails when column count does not match
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-asc" should have 99 columns
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-asc" to have 99 column(s), but found 3.
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should have :count column(s)" fails when the count is not an integer
    Given some behat configuration
    And scenario steps:
      """
      Then the table ".table-asc" should have "three" columns
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The count must be an integer, but "three" was given.
      """

  @phpserver
  Scenario: Assert "Then the table :selector should contain the following columns:" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-asc" should contain the following columns:
      | Name     |
      | Category |
      | Status   |

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should contain the following columns:" fails when column not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-asc" should contain the following columns:
        | NonExistent |
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ExpectationException" exception:
      """
      Column "NonExistent" not found in table ".table-asc".
      """

  @phpserver
  Scenario: Assert "Then the table :selector should be empty" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-empty" should be empty

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should be empty" fails when table has rows
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-asc" should be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-asc" to be empty, but found 3 row(s).
      """

  @phpserver
  Scenario: Assert "Then the table :selector should not be empty" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-asc" should not be empty

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should not be empty" fails when table is empty
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-empty" should not be empty
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-empty" to not be empty, but it has no rows.
      """

  @phpserver
  Scenario: Assert "Then the table :selector should be sorted by the column :column in :direction order" works with ascending order
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-asc" should be sorted by the column "Name" in "ascending" order

  @phpserver
  Scenario: Assert "Then the table :selector should be sorted by the column :column in :direction order" works with descending order
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-desc" should be sorted by the column "Name" in "descending" order

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should be sorted by the column :column in :direction order" fails with invalid direction
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-asc" should be sorted by the column "Name" in "invalid" order
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Invalid sort direction "invalid". Use "ascending" or "descending".
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should be sorted by the column :column in :direction order" fails when not sorted
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-desc" should be sorted by the column "Name" in "ascending" order
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected table ".table-desc" to be sorted by "Name" in ascending order.
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should be sorted by the column :column in :direction order" fails when column not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-asc" should be sorted by the column "NonExistent" in "ascending" order
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Column "NonExistent" not found in table ".table-asc".
      """

  @phpserver
  Scenario: Assert "Then the table :selector should contain the following rows:" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the table ".table-asc" should contain the following rows:
      | Name       | Status   |
      | Alpha item | Active   |
      | Beta item  | Inactive |

  @test-trait:TableTrait
  Scenario: Assert "Then the table :selector should contain the following rows:" fails when row not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the table ".table-asc" should contain the following rows:
        | Name         |
        | Non Existent |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      not found in table ".table-asc".
      """

  @phpserver
  Scenario: Assert "Then the row :row_text should contain the following:" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the row "Alpha item" should contain the following:
      | Type A  |
      | Active  |

  @test-trait:TableTrait
  Scenario: Assert "Then the row :row_text should contain the following:" fails when row not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the row "NonExistent" should contain the following:
        | some text |
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ElementNotFoundException" exception:
      """
      Table row with text "NonExistent" not found.
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the row :row_text should contain the following:" fails when text not found in row
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the row "Alpha item" should contain the following:
        | NonExistent |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Row containing "Alpha item" does not contain expected text "NonExistent".
      """

  @phpserver
  Scenario: Assert "When I click on the link :link in the row :row_text" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    And I click on the link "Edit" in the row "Epsilon record"
    Then the current URL should have the query parameter "edit" with the value "epsilon"

  @phpserver
  Scenario: Assert "When I press the button :button in the row :row_text" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    And I press the button "Remove" in the row "Epsilon record"
    Then the current URL should have the query parameter "remove" with the value "epsilon"

  @phpserver
  Scenario: Assert "Then the row :row_text should contain the value :value" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the row "Delta record" should contain the value "Draft"
    And the row "Delta record" should not contain the value "Published"

  @phpserver
  Scenario: Assert "Then the link :link should exist in the row :row_text" works as expected
    Given the user is anonymous
    When I visit "http://cli:8888/table.html"
    Then the link "Edit" should exist in the row "Delta record"
    And the link "Edit" should not exist in the row "Zeta record"

  @test-trait:TableTrait
  Scenario: Assert "When I click on the link :link in the row :row_text" fails when the row has no such link
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      And I click on the link "Edit" in the row "Zeta record"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ElementNotFoundException" exception:
      """
      Link in the row containing "Zeta record" with id|title|alt|text "Edit" not found.
      """

  @test-trait:TableTrait
  Scenario: Assert "When I press the button :button in the row :row_text" fails when the row has no such button
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      And I press the button "Remove" in the row "Zeta record"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Button in the row containing "Zeta record" with id|name|title|alt|value "Remove" not found.
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the row :row_text should contain the value :value" fails when no row has the text
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the row "Omega record" should contain the value "Draft"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Table row with text "Omega record" not found.
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the row :row_text should contain the value :value" fails when the row lacks the value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the row "Delta record" should contain the value "Published"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The row containing "Delta record" does not contain the text "Published".
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the row :row_text should not contain the value :value" fails when the row has the value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the row "Delta record" should not contain the value "Draft"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The row containing "Delta record" contains the text "Draft".
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the link :link should exist in the row :row_text" fails when the row has no such link
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the link "Edit" should exist in the row "Zeta record"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Link in the row containing "Zeta record" with id|title|alt|text "Edit" not found.
      """

  @test-trait:TableTrait
  Scenario: Assert "Then the link :link should not exist in the row :row_text" fails when the row has the link
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/table.html"
      Then the link "Edit" should not exist in the row "Delta record"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The row containing "Delta record" has the link "Edit".
      """
