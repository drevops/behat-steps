@d9
Feature: Check that BigPipeTrait works for or D9

  @api
  Scenario: Assert that Big Pipe cookie is set
    Given I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" exists

  @api @behat-steps-skip:bigPipeBeforeScenarioInit
  Scenario: Assert that Big Pipe cookie is not set when skip tag is used
    Given I install a "big_pipe" module
    When I visit "/"
    Then cookie "big_pipe_nojs" does not exist
