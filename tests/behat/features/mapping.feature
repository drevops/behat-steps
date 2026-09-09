Feature: Check that MappingTrait works
  As Behat Steps library developer
  I want to provide tools to replace configured tokens in step arguments
  So that users can name paths and values once and reuse them

  # An unknown key is covered by a unit test rather than a scenario: the
  # BehatCliContext harness uses "{{ }}" for its own template placeholders and
  # strips them from the generated feature file.

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
