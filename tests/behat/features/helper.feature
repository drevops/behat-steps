Feature: Check that TableTransposeTrait works

  Ensures that TableTransposeTrait provides reusable helper methods for table
  manipulation and processing, specifically the tableTransposeVertical() method.

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical works with single entity (2 columns)
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  | John  |
        | age   | 30    |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical works with multiple entities (3+ columns)
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  | John      | Jane      |
        | age   | 30        | 25        |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical throws exception for less than 2 columns
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Vertical table must have at least 2 columns (field name and value).
      """

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical throws exception for duplicate field names
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  | John  |
        | name  | Jane  |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Duplicate field names found: name
      """

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical throws exception for empty field names
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        |       | John  |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Field names cannot be empty.
      """

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical handles empty values in rows
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  | John   | Jane   |
        | age   | 30     |        |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical throws exception for single column table
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Vertical table must have at least 2 columns (field name and value).
      """

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical works with many entities (5+ columns)
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  | John     | Jane     | Bob      | Alice    |
        | age   | 30       | 25       | 35       | 28       |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical works with single field
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  | John  |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Helper\TableTransposeTrait
  Scenario: Assert tableTransposeVertical works with special characters in values
    Given some behat configuration
    And scenario steps:
      """
      When I call tableTransposeVertical with:
        | name  | O'Brien                |
        | email | test@example.com       |
        | bio   | Line 1\nLine 2         |
      """
    When I run "behat --no-colors"
    Then it should pass
