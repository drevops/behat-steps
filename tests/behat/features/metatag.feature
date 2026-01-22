Feature: Check that MetatagTrait works
  As Behat Steps library developer
  I want to provide tools to verify metatags on pages
  So that users can test SEO implementation

  @api
  Scenario: Assert that "Then the meta tag should exist with the following attributes:" step works as expected
    When I visit "/"
    Then the meta tag should exist with the following attributes:
      | name    | MobileOptimized |
      | content | width           |

  @trait:MetatagTrait
  Scenario: Assert that negative assertion for "Then the meta tag should exist with the following attributes:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
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
    When I visit "/"
    Then the meta tag should not exist with the following attributes:
      | name    | Non_Existing |
      | content | width        |

  @trait:MetatagTrait
  Scenario: Assert that negative assertion for "Then the meta tag should not exist with the following attributes:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the meta tag should not exist with the following attributes:
        | name    | MobileOptimized |
        | content | width           |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with specified attributes should not exist: {"name":"MobileOptimized","content":"width"}
      """
