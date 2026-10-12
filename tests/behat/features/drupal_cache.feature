Feature: Check that CacheTrait works
  As Behat Steps library developer
  I want to provide tools for targeted Drupal cache invalidation
  So that users can clear specific caches in their tests without a full rebuild

  Scenario: Assert "Given the page cache for the path :path has been cleared" clears only that path
    Given the page cache for the paths matching "/user/*" has been cleared
    When I go to "/user/login"
    And I go to "/user/login"
    Then the response header "X-Drupal-Cache" should contain "HIT"
    When I go to "/user/login?page=1"
    And I go to "/user/password"
    And the page cache for the path "/user/login" has been cleared
    And I go to "/user/login"
    Then the response header "X-Drupal-Cache" should contain "MISS"
    When I go to "/user/login?page=1"
    Then the response header "X-Drupal-Cache" should contain "MISS"
    When I go to "/user/password"
    Then the response header "X-Drupal-Cache" should contain "HIT"

  Scenario: Assert "Given the page cache for the paths matching :path_pattern has been cleared" clears only the matching paths
    Given the page cache for the paths matching "/user/*" has been cleared
    When I go to "/user/login"
    And I go to "/user/password"
    And the page cache for the paths matching "/pass*" has been cleared
    And I go to "/user/password"
    Then the response header "X-Drupal-Cache" should contain "HIT"
    When the page cache for the paths matching "/user/pass*" has been cleared
    And I go to "/user/password"
    Then the response header "X-Drupal-Cache" should contain "MISS"
    When I go to "/user/login"
    Then the response header "X-Drupal-Cache" should contain "HIT"

  Scenario: Assert "Given the render cache has been cleared" clears the render cache
    Given I am logged in as a user with the "administrator" role and I have the following fields:
      | name | cache_render_admin |
    And the render cache has been cleared
    When I go to "/user"
    Then I should see "cache_render_admin"

  Scenario: Assert "When I run cron" runs cron
    Given the watchdog is cleared
    When I run cron
    And I am logged in as a user with the "administrator" role
    And I go to "/admin/reports/dblog"
    Then I should see "Cron run completed."

  # The cache clear and cron steps resolve their capability instead of
  # bootstrapping Drupal, so '@backend:drush' runs them over Drush.
  @backend:drush
  Scenario: Assert "Given the cache has been cleared" clears every cache over Drush
    Given the page cache for the paths matching "/user/*" has been cleared
    When I go to "/user/login"
    And I go to "/user/login"
    Then the response header "X-Drupal-Cache" should contain "HIT"
    When the cache has been cleared
    And I go to "/user/login"
    Then the response header "X-Drupal-Cache" should contain "MISS"

  @backend:drush
  Scenario: Assert "When I run cron" runs cron over Drush
    Given the watchdog is cleared
    When I run cron
    And I am logged in as a user with the "administrator" role
    And I go to "/admin/reports/dblog"
    Then I should see "Cron run completed."

  @test-trait:Drupal\CacheTrait
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

  @test-trait:Drupal\CacheTrait
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

  @test-trait:Drupal\CacheTrait
  Scenario: Assert clearing the page cache with a path carrying a query string fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the page cache for the path "/about?page=1" has been cleared
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The path "/about?page=1" must not contain a query string or a fragment.
      """

  @test-trait:Drupal\CacheTrait
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

  @test-trait:Drupal\CacheTrait
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

  @test-trait:Drupal\CacheTrait
  Scenario: Assert clearing the page cache with a pattern carrying a fragment fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the page cache for the paths matching "/news*#top" has been cleared
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The path pattern "/news*#top" must not contain a query string or a fragment.
      """
