Feature: Check that MappingTrait works
  As Behat Steps library developer
  I want to provide tools to replace configured tokens in step arguments
  So that users can name paths and values once and reuse them

  @api
  Scenario: Assert that a mapping token resolves in a step argument
    Given the user is anonymous
    When I visit "{{ User Login }}"
    Then the path should be "/user/login"

  @api
  Scenario: Assert that a mapping token resolves without surrounding whitespace
    Given the user is anonymous
    When I visit "{{User Registration}}"
    Then the path should be "/user/register"

  @trait:MappingTrait
  Scenario: Assert that an unknown mapping key fails the step
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      When I visit "{{ Nonexistent Key }}"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No such mapping: Nonexistent Key
      """
