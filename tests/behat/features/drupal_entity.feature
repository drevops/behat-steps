Feature: Check that EntityTrait works
  As Behat Steps library developer
  I want to provide a step that creates entities of any type
  So that users can cover entity types without a dedicated trait

  @api
  Scenario: Assert "Given the following :entity_type entities exist:" works as expected
    Given the following "block_content" entities exist:
      | info                | type  |
      | [TEST] Basic block  | basic |
    When I log in as a user with the "administrator" role
    And I visit "/admin/content/block"
    Then I should see "[TEST] Basic block"

  @api @behat-steps-entity-cleanup-skip:block_content
  Scenario: Assert that a per-type cleanup bypass tag leaves the entity in place
    Given the following "block_content" entities exist:
      | info               | type  |
      | [TEST] Kept block  | basic |
    When I log in as a user with the "administrator" role
    And I visit "/admin/content/block"
    Then I should see "[TEST] Kept block"
