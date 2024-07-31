Feature: Check that MetaTagTrait works

  @api
  Scenario: Assert that a meta tag with specific attributes and values exists/does not exist.
    Given I visit "/"
    Then I should see a meta tag with the following attributes:
      | name    | MobileOptimized |
      | content | width           |
    And I should not see a meta tag with the following attributes:
      | name    | Non_Existing |
      | content | width        |

  @trait:MetaTagTrait
  Scenario: Assert that a meta tag with specific attributes and values exists/does not exist.
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      Then I should not see a meta tag with the following attributes:
        | name    | MobileOptimized |
        | content | width           |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with specified attributes should not exist: {"name":"MobileOptimized","content":"width"}
      """

  @trait:MetaTagTrait
  Scenario: Assert that a meta tag with specific attributes and values exists/does not exist.
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      Then I should see a meta tag with the following attributes:
        | name    | Non_Existing |
        | content | width        |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with specified attributes was not found: {"name":"Non_Existing","content":"width"}
      """
