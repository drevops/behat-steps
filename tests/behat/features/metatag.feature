Feature: Check that MetaTagTrait works

  @api
  Scenario: Assert that "Then the meta tag should exist with the following attributes:" step works as expected
    Given I visit "/"
    Then the meta tag should exist with the following attributes:
      | name    | MobileOptimized |
      | content | width           |

  @trait:MetaTagTrait
  Scenario: Assert that negative assertion for "Then the meta tag should exist with the following attributes:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      Then the meta tag should exist with the following attributes:
        | name    | Non_Existing |
        | content | width        |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with specified attributes was not found: {"name":"Non_Existing","content":"width"}
      """

  @api
  Scenario: Assert that "Then the meta tag should not exist with the following attributes:" step works as expected
    Given I visit "/"
    Then the meta tag should not exist with the following attributes:
      | name    | Non_Existing |
      | content | width        |

  @trait:MetaTagTrait
  Scenario: Assert that negative assertion for "Then the meta tag should not exist with the following attributes:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      Then the meta tag should not exist with the following attributes:
        | name    | MobileOptimized |
        | content | width           |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with specified attributes should not exist: {"name":"MobileOptimized","content":"width"}
      """
