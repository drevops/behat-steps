Feature: Check that CacheTrait works
  As Behat Steps library developer
  I want to provide tools for targeted Drupal cache invalidation
  So that users can clear specific caches in their tests without a full rebuild

  @api
  Scenario: Assert "Given the page cache for the path :path is empty" clears a single path
    When I log in as a user with the "administrator" role
    And the page cache for the path "/user" is empty
    When I go to "/user"
    Then I should see "Member for"

  @api
  Scenario: Assert "Given the page cache for the paths matching :path_pattern is empty" clears matching paths
    When I log in as a user with the "administrator" role
    And the page cache for the paths matching "/user*" is empty
    When I go to "/user"
    Then I should see "Member for"

  @api
  Scenario: Assert "Given the render cache is empty" clears the render cache
    When I log in as a user with the "administrator" role
    And the render cache is empty
    When I go to "/user"
    Then I should see "Member for"

  @api @trait:Drupal\CacheTrait
  Scenario: Assert clearing the page cache with an empty path fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the page cache for the path "" is empty
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The path must not be empty.
      """

  @api @trait:Drupal\CacheTrait
  Scenario: Assert clearing the page cache with a path missing a leading slash fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the page cache for the path "about" is empty
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The path "about" must start with a leading slash.
      """

  @api @trait:Drupal\CacheTrait
  Scenario: Assert clearing the page cache with an empty pattern fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the page cache for the paths matching "" is empty
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The path pattern must not be empty.
      """

  @api @trait:Drupal\CacheTrait
  Scenario: Assert clearing the page cache with a pattern missing a leading slash fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the page cache for the paths matching "news/*" is empty
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The path pattern "news/*" must start with a leading slash.
      """
