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

  # The paired scenarios below rely on Behat running scenarios in file order:
  # the first keeps its entity past teardown, the second proves it survived and
  # removes it so later scenarios do not inherit it.

  @api @behat-steps-entity-cleanup-skip:block_content
  Scenario: The per-type skip tag keeps entities of the named type
    Given the following "block_content" entities exist:
      | info               | type  |
      | [TEST] Kept block  | basic |
    When I log in as a user with the "administrator" role
    And I visit "/admin/content/block"
    Then I should see "[TEST] Kept block"

  @api
  Scenario: An entity kept by the per-type skip tag survives teardown and is removed manually
    Given the following "basic" content blocks do not exist:
      | [TEST] Kept block |
    When I log in as a user with the "administrator" role
    And I visit "/admin/content/block"
    Then I should not see "[TEST] Kept block"
