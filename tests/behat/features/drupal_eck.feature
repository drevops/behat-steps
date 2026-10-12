Feature: Check that EckTrait works
  As Behat Steps library developer
  I want to be able to manage ECK entities in my tests
  So that I can verify ECK entity functionality

  Background:
    Given the following ECK "test_bundle" "test_entity_type" entities do not exist:
      | title             |
      | [TEST] ECK Entity |
    And the following "tags" terms exist:
      | name |
      | T2   |
    And the following ECK "test_bundle" "test_entity_type" entities exist:
      | title            | field_test_text | field_test_reference |
      | [TEST] ECK test1 | Test text field | T2                   |

  Scenario: Assert "I visit the ECK :bundle :entity_type entity page with the title :title" works as expected
    Given I am logged in as a user with the role "administrator"
    When I visit the ECK "test_bundle" "test_entity_type" entity page with the title "[TEST] ECK test1"
    Then I should see "[TEST] ECK test1"
    And I should see "T2"

  @test-trait:Drupal\EckTrait
  Scenario: Assert navigate "I visit the ECK :bundle :entity_type entity page with the title :title" works as expected
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the ECK "test_bundle" "test_entity_type" entity page with the title "[TEST] ECK Entity non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "test_entity_type" page "[TEST] ECK Entity non-existing"
      """

  Scenario: Assert "When I visit the ECK :bundle :entity_type entity edit page with the title :title" works as expected
    Given I am logged in as a user with the role "administrator"
    When I visit the ECK "test_bundle" "test_entity_type" entity edit page with the title "[TEST] ECK test1"
    Then I should see "Edit test bundle [TEST] ECK test1"

  @test-trait:Drupal\EckTrait
  Scenario: Assert negative "When I visit the ECK :bundle :entity_type entity edit page with the title :title" works as expected
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the ECK "test_bundle" "test_entity_type" entity edit page with the title "[TEST] ECK Entity non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "test_entity_type" page "[TEST] ECK Entity non-existing"
      """

  @behat-steps-entity-cleanup-skip:test_entity_type
  Scenario: ECK entities are not automatically cleaned up when skip tag is used
    Given the following ECK "test_bundle" "test_entity_type" entities exist:
      | title                   | field_test_text   |
      | [TEST] Skip Cleanup ECK | Skip cleanup test |
    And I am logged in as a user with the role "administrator"
    When I visit the ECK "test_bundle" "test_entity_type" entity page with the title "[TEST] Skip Cleanup ECK"
    Then I should see "[TEST] Skip Cleanup ECK"
    Given the following ECK "test_bundle" "test_entity_type" entities do not exist:
      | title                   |
      | [TEST] Skip Cleanup ECK |

  Scenario: ECK entities are automatically cleaned up after scenario
    Given the following ECK "test_bundle" "test_entity_type" entities exist:
      | title                     | field_test_text     |
      | [TEST] Auto Cleanup ECK 1 | Auto cleanup test 1 |
      | [TEST] Auto Cleanup ECK 2 | Auto cleanup test 2 |
    And I am logged in as a user with the role "administrator"
    When I visit the ECK "test_bundle" "test_entity_type" entity page with the title "[TEST] Auto Cleanup ECK 1"
    Then I should see "[TEST] Auto Cleanup ECK 1"
