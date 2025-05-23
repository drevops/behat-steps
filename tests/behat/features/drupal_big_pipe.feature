Feature: Check that BigPipeTrait works
  As Behat Steps library developer
  I want to provide tools to manage BigPipe cookies
  So that users can test progressive rendering with and without JavaScript

  @api
  Scenario: Assert that Big Pipe cookie is set
    Given I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" exists

  @api @behat-steps-skip:bigPipeBeforeScenario
  Scenario: Assert that Big Pipe cookie is not set when skip tag is used
    Given I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" does not exist

  @api
  Scenario: Assert that Big Pipe cookie is preserved across multiple users in a scenario
    Given users:
      | name               | mail                             | roles         | status |
      | administrator_user | administrator_user@myexample.com | administrator | 1      |
    And I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" exists
    When I am logged in as "administrator_user"
    And I visit "/"
    Then cookie "big_pipe_nojs" exists

  @api @behat-steps-skip:bigPipeBeforeStep
  Scenario: Assert that Big Pipe cookie is not preserved across multiple users when skip tag is used
    Given users:
      | name               | mail                             | roles         | status |
      | administrator_user | administrator_user@myexample.com | administrator | 1      |
    And I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" exists
    # Logging in as a new user removes cookies.
    When I am logged in as "administrator_user"
    And I visit "/"
    Then cookie "big_pipe_nojs" does not exist
