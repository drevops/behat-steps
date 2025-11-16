Feature: Check that HelperTrait works

  Ensures that the HelperTrait provides reusable helper methods for table
  manipulation and processing, specifically the transposeVerticalTable() method.

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable works with single entity (2 columns)
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  | John  |
        | age   | 30    |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable works with multiple entities (3+ columns)
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  | John      | Jane      |
        | age   | 30        | 25        |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable throws exception for less than 2 columns
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Vertical table must have at least 2 columns (field name and value).
      """

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable throws exception for duplicate field names
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  | John  |
        | name  | Jane  |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Duplicate field names found: name
      """

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable throws exception for empty field names
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        |       | John  |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Field names cannot be empty.
      """

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable handles empty values in rows
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  | John   | Jane   |
        | age   | 30     |        |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable throws exception for single column table
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Vertical table must have at least 2 columns (field name and value).
      """

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable works with many entities (5+ columns)
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  | John     | Jane     | Bob      | Alice    |
        | age   | 30       | 25       | 35       | 28       |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable works with single field
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  | John  |
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:HelperTrait
  Scenario: Assert transposeVerticalTable works with special characters in values
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I call helperTransposeVerticalTable with:
        | name  | O'Brien                |
        | email | test@example.com       |
        | bio   | Line 1\nLine 2         |
      """
    When I run "behat --no-colors"
    Then it should pass
