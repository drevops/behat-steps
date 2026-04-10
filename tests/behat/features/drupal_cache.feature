Feature: Check that CacheTrait works
  As Behat Steps library developer
  I want to provide tools for targeted Drupal cache invalidation
  So that users can clear specific caches in their tests without a full rebuild

  @api
  Scenario: Assert "Given the page cache for the path :path has been cleared" clears a single path
    Given I am logged in as a user with the "administrator" role
    When the page cache for the path "/user" has been cleared
    Then I go to "/user"

  @api
  Scenario: Assert "Given the page cache for the paths matching :path_pattern has been cleared" clears matching paths
    Given I am logged in as a user with the "administrator" role
    When the page cache for the paths matching "/user*" has been cleared
    Then I go to "/user"

  @api
  Scenario: Assert "Given the render cache has been cleared" clears the render cache
    Given I am logged in as a user with the "administrator" role
    When the render cache has been cleared
    Then I go to "/user"

  @api @trait:Drupal\CacheTrait
  Scenario: Assert clearing the page cache with an empty path fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the page cache for the path "" has been cleared
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
      And the page cache for the path "about" has been cleared
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
      And the page cache for the paths matching "" has been cleared
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
      And the page cache for the paths matching "news/*" has been cleared
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The path pattern "news/*" must start with a leading slash.
      """
