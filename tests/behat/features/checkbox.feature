Feature: Check that CheckboxTrait works
  As Behat Steps library developer
  I want to provide tools to handle checkbox form elements
  So that users can efficiently manage checkbox state in their tests

  @javascript @phpserver
  Scenario: Ensure already checked checkbox remains checked
    Given I am on the phpserver test page
    When I check "Test checkbox"
    Then the "Test checkbox" checkbox should be checked
    When I ensure the box "Test checkbox" is checked
    Then the "Test checkbox" checkbox should be checked

  @javascript @phpserver
  Scenario: Ensure already unchecked checkbox remains unchecked
    Given I am on the phpserver test page
    When I uncheck "Test checkbox"
    Then the "Test checkbox" checkbox should not be checked
    When I ensure the box "Test checkbox" is unchecked
    Then the "Test checkbox" checkbox should not be checked

  @javascript @phpserver
  Scenario: Ensure can change checkbox from unchecked to checked
    Given I am on the phpserver test page
    When I uncheck "Test checkbox"
    Then the "Test checkbox" checkbox should not be checked
    When I ensure the box "Test checkbox" is checked
    Then the "Test checkbox" checkbox should be checked

  @javascript @phpserver
  Scenario: Ensure can change checkbox from checked to unchecked
    Given I am on the phpserver test page
    When I check "Test checkbox"
    Then the "Test checkbox" checkbox should be checked
    When I ensure the box "Test checkbox" is unchecked
    Then the "Test checkbox" checkbox should not be checked

  @javascript @phpserver
  Scenario: Multiple ensure operations are idempotent
    Given I am on the phpserver test page
    # Multiple checks
    When I ensure the box "Test checkbox" is checked
    Then the "Test checkbox" checkbox should be checked
    When I ensure the box "Test checkbox" is checked
    Then the "Test checkbox" checkbox should be checked
    # Multiple unchecks
    When I ensure the box "Test checkbox" is unchecked
    Then the "Test checkbox" checkbox should not be checked
    When I ensure the box "Test checkbox" is unchecked
    Then the "Test checkbox" checkbox should not be checked

  @trait:CheckboxTrait @skipped
  Scenario: Negative assertion for "Given I ensure the box :label is checked" fails as expected when the checkbox does not exist
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am on the phpserver test page
      When I ensure the box "Non-existent checkbox" is checked
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The checkbox with label "Non-existent checkbox" was not found on the page.
      """

  @trait:CheckboxTrait @skipped
  Scenario: Negative assertion for "Given I ensure the box :label is unchecked" fails as expected when the checkbox does not exist
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am on the phpserver test page
      When I ensure the box "Non-existent checkbox" is unchecked
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The checkbox with label "Non-existent checkbox" was not found on the page.
      """
