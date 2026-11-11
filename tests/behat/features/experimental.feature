Feature: Experimental code paths

  As a developer running a suite on a pre-release dependency
  I want to know which workarounds the run relied on
  So that I can tell when a workaround is no longer needed

  @test-trait:PathTrait
  Scenario: Assert that a run with no experimental code path lists none
    Given some behat configuration
    And scenario steps:
      """
      Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should pass
    And the output should not contain:
      """
      Experimental behavior used in this run
      """

  @test-trait:PathTrait
  Scenario: Assert that a run lists each experimental code path once with its count
    Given some behat configuration
    And scenario steps:
      """
      When I record the test experimental notice "test.notice"
      And I record the test experimental notice "test.notice"
      And I take the test experimental fallback "test.fallback"
      """
    When I run "behat --no-colors"
    Then it should pass
    And the output should contain:
      """
      --- Experimental behavior used in this run:

          [test.notice] The test notice was recorded. (2 times)
          [test.fallback] The test fallback absorbed a failure. (1 time)
      """

  @test-trait:PathTrait
  Scenario: Assert that strict mode throws the failure a fallback would absorb
    Given a configuration with the extension options:
      """
      'experimental' => ['strict' => TRUE],
      """
    And some behat configuration
    And scenario steps:
      """
      When I take the test experimental fallback "test.fallback"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The test failure the fallback absorbs.
      """
    And the output should contain:
      """
      [test.fallback] The test fallback absorbed a failure. (1 time)
      """

  @test-trait:PathTrait
  Scenario: Assert that strict mode still lists the experimental code paths that ran
    Given a configuration with the extension options:
      """
      'experimental' => ['strict' => TRUE],
      """
    And some behat configuration
    And scenario steps:
      """
      When I record the test experimental notice "test.notice"
      """
    When I run "behat --no-colors"
    Then it should pass
    And the output should contain:
      """
      [test.notice] The test notice was recorded. (1 time)
      """
