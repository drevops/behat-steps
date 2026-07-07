Feature: Check that BigPipeTrait works
  As Behat Steps library developer
  I want BigPipe handled for both non-JavaScript and JavaScript drivers
  So that non-JS drivers bypass streaming via the nojs cookie and JS drivers wait for placeholders to be replaced before assertions

  @api @skipped
  Scenario: Assert that Big Pipe cookie is set
    Given I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" exists

  @api @behat-steps-skip:bigPipeBeforeScenario
  Scenario: Assert that Big Pipe cookie is not set when skip tag is used
    Given I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" does not exist

  @api @skipped
  Scenario: Assert that Big Pipe cookie is preserved across multiple users in a scenario
    Given the following users:
      | name               | mail                             | roles         | status |
      | administrator_user | administrator_user@myexample.com | administrator | 1      |
    And I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" exists
    When I am logged in as "administrator_user"
    And I visit "/"
    Then cookie "big_pipe_nojs" exists

  @api @behat-steps-skip:bigPipeBeforeStep @skipped
  Scenario: Assert that Big Pipe cookie is not preserved across multiple users when skip tag is used
    Given the following users:
      | name               | mail                             | roles         | status |
      | administrator_user | administrator_user@myexample.com | administrator | 1      |
    And I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" exists
    # Logging in as a new user removes cookies.
    When I am logged in as "administrator_user"
    And I visit "/"
    Then cookie "big_pipe_nojs" does not exist

  @api @javascript
  Scenario: Assert that Big Pipe cookie is not set when JavaScript is supported
    Given I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" does not exist

  @javascript
  Scenario: BigPipe placeholders are awaited automatically before assertions
    When I visit "/sites/default/files/bigpipe_wait.html"
    Then I should see "BigPipe streamed content"

  @javascript @behat-steps-skip:BigPipeTrait
  Scenario: The automatic BigPipe wait can be skipped with a tag
    When I visit "/sites/default/files/bigpipe_wait.html"
    Then I should see an "span[data-big-pipe-placeholder-id]" element
    And I should not see "BigPipe streamed content"

  @javascript @bigpipe-timeout
  Scenario: The automatic BigPipe wait times out without failing when placeholders never resolve
    When I visit "/sites/default/files/bigpipe_stuck.html"
    Then I should see an "span[data-big-pipe-placeholder-id]" element
    And I should not see "BigPipe streamed content"
