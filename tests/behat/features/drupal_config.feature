@config
Feature: Check that ConfigTrait works
  As Behat Steps library developer
  I want to provide tools to set and assert Drupal configuration values
  So that users can assert stored and effective config during tests with automatic revert

  @api
  Scenario: Set and assert a stored string config value
    Given the config "behat_steps_test.settings" key "endpoint" has the value "https://api.example.com"
    Then the config "behat_steps_test.settings" key "endpoint" should have the value "https://api.example.com"

  @api
  Scenario: Set and assert a stored nested (dotted) config value
    Given the config "behat_steps_test.settings" key "api.endpoint" has the value "https://nested.example.com"
    Then the config "behat_steps_test.settings" key "api.endpoint" should have the value "https://nested.example.com"

  @api
  Scenario: Set multiple config values of different types from a table
    Given the following config values:
      | name                   | key      | value           |
      | behat_steps_test.types | enabled  | true            |
      | behat_steps_test.types | disabled | false           |
      | behat_steps_test.types | count    | 30              |
      | behat_steps_test.types | ratio    | 1.5             |
      | behat_steps_test.types | label    | hello world     |
      | behat_steps_test.types | tags     | ["a","b","c"]   |
      | behat_steps_test.types | map      | {"k":"v"}       |
      | behat_steps_test.types | nested   | {"a":["x","y"]} |
      | behat_steps_test.types | empty    |                 |
      | behat_steps_test.types | broken   | [a,b]           |
    Then the config "behat_steps_test.types" key "enabled" should have the value "true"
    And the config "behat_steps_test.types" key "disabled" should have the value "false"
    And the config "behat_steps_test.types" key "count" should have the value "30"
    And the config "behat_steps_test.types" key "ratio" should have the value "1.5"
    And the config "behat_steps_test.types" key "label" should have the value "hello world"
    And the config "behat_steps_test.types" key "tags" should contain the value "b"
    And the config "behat_steps_test.types" key "map" should contain the value "v"
    And the config "behat_steps_test.types" key "nested" should contain the value "y"
    And the config "behat_steps_test.types" key "nested" should not contain the value "z"
    And the config "behat_steps_test.types" key "empty" should have the value ""
    And the config "behat_steps_test.types" key "broken" should have the value "[a,b]"

  @api
  Scenario: Assert stored string containment and its negations
    Given the config "behat_steps_test.settings" key "endpoint" has the value "https://api.example.com/v2"
    Then the config "behat_steps_test.settings" key "endpoint" should contain the value "example.com"
    And the config "behat_steps_test.settings" key "endpoint" should not contain the value "other.org"
    And the config "behat_steps_test.settings" key "endpoint" should not have the value "https://api.example.com"

  @api
  Scenario: Assert stored array membership and its negation
    Given the following config values:
      | name                 | key   | value                 |
      | behat_steps_test.arr | roles | ["editor","reviewer"] |
    Then the config "behat_steps_test.arr" key "roles" should contain the value "editor"
    And the config "behat_steps_test.arr" key "roles" should not contain the value "admin"

  @api
  Scenario: Assert an unset stored config key neither has nor contains a value
    Then the config "behat_steps_test.settings" key "missing" should not have the value "anything"
    And the config "behat_steps_test.settings" key "missing" should not contain the value "anything"

  @api
  Scenario: Setting a config value to null leaves the key unset
    Given the config "behat_steps_test.settings" key "cleared" has the value "null"
    Then the config "behat_steps_test.settings" key "cleared" should not have the value "null"

  @api
  Scenario: Stored assertion reads the saved value ignoring settings.php overrides
    Then the config "system.site" key "name" should have the value "Drush Site-Install"
    And the config "system.site" key "name" should contain the value "Drush"

  @api
  Scenario: Effective assertion reads the settings.php-overridden value
    Then the config "system.site" key "name" should have the effective value "Overridden Site Name"
    And the config "system.site" key "name" should contain the effective value "Overridden"

  @api
  Scenario: Stored and effective values differ for an overridden key
    Then the config "system.site" key "name" should have the value "Drush Site-Install"
    And the config "system.site" key "name" should not have the value "Overridden Site Name"
    And the config "system.site" key "name" should have the effective value "Overridden Site Name"
    And the config "system.site" key "name" should not have the effective value "Drush Site-Install"
    And the config "system.site" key "name" should not contain the effective value "Drush"

  # Setup scenario: store a known value so the next scenario can prove it got
  # reverted automatically. The AfterScenario hook is skipped here so the value
  # persists into the following scenario.
  @api @behat-steps-skip:configAfterScenario
  Scenario: Seed a config value without auto-revert
    Given the config "behat_steps_test.persistent" key "flag" has the value "seeded"
    Then the config "behat_steps_test.persistent" key "flag" should have the value "seeded"

  @api
  Scenario: Config values are reverted after scenario
    Given the config "behat_steps_test.persistent" key "flag" has the value "overridden"
    Then the config "behat_steps_test.persistent" key "flag" should have the value "overridden"

  @api
  Scenario: Verify previous scenario config change was reverted to the seeded value
    Then the config "behat_steps_test.persistent" key "flag" should have the value "seeded"

  @api
  Scenario: A newly created config object is set during the scenario
    Given the config "behat_steps_test.ephemeral" key "foo" has the value "bar"
    Then the config "behat_steps_test.ephemeral" key "foo" should have the value "bar"

  @api
  Scenario: Verify the new config object was deleted by the previous revert
    Then the config "behat_steps_test.ephemeral" key "foo" should not have the value "bar"

  @api @trait:Drupal\ConfigTrait
  Scenario: Negative assertion for "should have the value" fails on value mismatch
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com"
      Then the config "behat_steps_test.settings" key "endpoint" should have the value "https://b.example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com", but it should have the value "https://b.example.com".
      """

  @api @trait:Drupal\ConfigTrait
  Scenario: Negative assertion for "should have the value" fails when the key is not set
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the config "behat_steps_test.absent" key "endpoint" should have the value "https://a.example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The config "behat_steps_test.absent" key "endpoint" is not set, but it should have the value "https://a.example.com".
      """

  @api @trait:Drupal\ConfigTrait
  Scenario: Negative assertion for "should not have the value" fails when it matches
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com"
      Then the config "behat_steps_test.settings" key "endpoint" should not have the value "https://a.example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com", but it should not have the value "https://a.example.com".
      """

  @api @trait:Drupal\ConfigTrait
  Scenario: Negative assertion for "should contain the value" fails when the value is absent
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com"
      Then the config "behat_steps_test.settings" key "endpoint" should contain the value "zzz"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com", which does not contain "zzz".
      """

  @api @trait:Drupal\ConfigTrait
  Scenario: Negative assertion for "should contain the value" fails when the key is not set
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the config "behat_steps_test.absent" key "endpoint" should contain the value "example"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The config "behat_steps_test.absent" key "endpoint" is not set, but its value should contain "example".
      """

  @api @trait:Drupal\ConfigTrait
  Scenario: Negative assertion for "should not contain the value" fails when the value is present
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com"
      Then the config "behat_steps_test.settings" key "endpoint" should not contain the value "example"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The config "behat_steps_test.settings" key "endpoint" has the value "https://a.example.com", which contains "example" but should not.
      """

  @api @trait:Drupal\ConfigTrait
  Scenario: A config values table missing the value column fails with an exception
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the following config values:
        | name                      | key    |
        | behat_steps_test.settings | broken |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The config values table must contain "name", "key" and "value" columns.
      """
