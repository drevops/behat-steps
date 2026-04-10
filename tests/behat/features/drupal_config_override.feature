@config-override
Feature: Check that ConfigOverrideTrait works
  As Behat Steps library developer
  I want to provide a way to disable Drupal config overrides for a scenario
  So that users can test original config values without redeploying the SUT

  # The fixture site has the following overrides in settings.php:
  #   $config['system.site']['name']   = 'Overridden Site Name';
  #   $config['system.site']['slogan'] = 'Overridden Slogan';
  # The stored (original) name is 'Drush Site-Install'.

  @api
  Scenario: Without the tag, the SUT serves the settings.php-overridden config value
    When I visit "/mysite_core/test-config-system-site-name"
    Then the response status code should be 200
    And the response should contain "Overridden Site Name"

  @api @disable-config-override:system.site
  Scenario: With @disable-config-override, the SUT serves the original stored config value
    When I visit "/mysite_core/test-config-system-site-name"
    Then the response status code should be 200
    And the response should contain "Drush Site-Install"
    And the response should not contain "Overridden Site Name"

  @api
  Scenario: Visiting a page without the tag sends no X-Config-No-Override header
    When I visit "/mysite_core/test-config-no-override-header"
    Then the response status code should be 200
    And the response should not contain "system.site"

  @api @disable-config-override:system.site
  Scenario: A single @disable-config-override tag sets the X-Config-No-Override header
    When I visit "/mysite_core/test-config-no-override-header"
    Then the response status code should be 200
    And the response should contain "system.site"

  @api @disable-config-override:system.site @disable-config-override:myconfig.settings
  Scenario: Multiple @disable-config-override tags set a comma-separated X-Config-No-Override header
    When I visit "/mysite_core/test-config-no-override-header"
    Then the response status code should be 200
    And the response should contain "system.site,myconfig.settings"

  @api @disable-config-override:system.site
  Scenario: The X-Config-No-Override header survives a login step that resets headers
    Given users:
      | name      | mail                  | roles         | status |
      | test_user | test_user@example.com | administrator | 1      |
    When I am logged in as "test_user"
    And I visit "/mysite_core/test-config-no-override-header"
    Then the response status code should be 200
    And the response should contain "system.site"

  @api @disable-config-override:system.site @behat-steps-skip:configOverrideBeforeScenario
  Scenario: The @behat-steps-skip:configOverrideBeforeScenario tag bypasses the trait entirely
    When I visit "/mysite_core/test-config-no-override-header"
    Then the response status code should be 200
    And the response should not contain "system.site"

  @api @disable-config-override:system.site @behat-steps-skip:configOverrideBeforeStep
  Scenario: The @behat-steps-skip:configOverrideBeforeStep tag keeps tag parsing but skips header propagation
    Given users:
      | name       | mail                   | roles         | status |
      | test_user2 | test_user2@example.com | administrator | 1      |
    When I am logged in as "test_user2"
    And I visit "/mysite_core/test-config-no-override-header"
    Then the response status code should be 200
    And the response should not contain "system.site"
