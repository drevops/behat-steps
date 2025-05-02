Feature: Check that EckTrait works
  As Behat Steps library developer
  I want to be able to manage ECK entities in my tests
  So that I can verify ECK entity functionality

  Background:
    Given the following eck "test_bundle" "test_entity_type" entities do not exist:
      | title             |
      | [TEST] ECK Entity |
    And "tags" terms:
      | name |
      | T2   |
    And the following eck "test_bundle" "test_entity_type" entities exist:
      | title            | field_test_text | field_test_reference |
      | [TEST] ECK test1 | Test text field | T2                   |

  @api
  Scenario: Assert "I visit eck :bundle :entity_type entity with the title :title" works as expected.
    Given I am logged in as a user with the "administrator" role
    When I visit eck "test_bundle" "test_entity_type" entity with the title "[TEST] ECK test1"
    Then I should see "[TEST] ECK test1"
    And I should see "T2"

  @api @trait:EckTrait
  Scenario: Assert navigate "I visit eck :bundle :entity_type entity with the title :title" works as expected.
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit eck "test_bundle" "test_entity_type" entity with the title "[TEST] ECK Entity non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "test_entity_type" page "[TEST] ECK Entity non-existing"
      """

  @api
  Scenario: Assert "When I edit eck :bundle :entity_type entity with the title :title" works as expected.
    Given I am logged in as a user with the "administrator" role
    When I edit eck "test_bundle" "test_entity_type" entity with the title "[TEST] ECK test1"
    Then I should see "Edit test bundle [TEST] ECK test1"

  @api @trait:EckTrait
  Scenario: Assert negative "When I edit eck :bundle :entity_type entity with the title :title" works as expected.
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I edit eck "test_bundle" "test_entity_type" entity with the title "[TEST] ECK Entity non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "test_entity_type" page "[TEST] ECK Entity non-existing"
      """
