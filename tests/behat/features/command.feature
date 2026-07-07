Feature: Check that CommandTrait works
  As Behat Steps library developer
  I want to provide tools to run shell commands and assert on their result
  So that scenarios can shell out and verify command outcomes

  Scenario: Assert that a successful command passes success, output, and exit code assertions
    When I run the command "echo hello"
    Then the command should succeed
    And the command exit code should be 0
    And the command output should contain "hello"
    And the command output should be "hello"
    And the command output should not contain "goodbye"

  Scenario: Assert that a failing command passes failure and exit code assertions
    When I run the command "exit 3"
    Then the command should fail
    And the command exit code should be 3

  Scenario: Assert that error output is captured separately from standard output
    When I run the command "echo oops >&2"
    Then the command error output should contain "oops"
    And the command output should not contain "oops"

  Scenario: Assert that duration assertions pass and a second command replaces the first
    When I run the command "echo fast"
    Then the command should complete in less than 60 seconds
    When I run the command "sleep 1"
    Then the command should complete in more than 0 seconds

  @trait:CommandTrait
  Scenario: Assert that "the command should succeed" fails when the command failed
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "exit 1"
      Then the command should succeed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command to succeed, but it exited with code 1.
      """

  @trait:CommandTrait
  Scenario: Assert that "the command should fail" fails when the command succeeded
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command should fail
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command to fail, but it exited with code 0.
      """

  @trait:CommandTrait
  Scenario: Assert that "the command exit code should be" fails on a mismatch
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command exit code should be 3
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command to exit with code 3, but it exited with code 0.
      """

  @trait:CommandTrait
  Scenario: Assert that "the command output should contain" fails when the text is absent
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command output should contain "goodbye"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command output to contain "goodbye", but it did not.
      """

  @trait:CommandTrait
  Scenario: Assert that "the command output should not contain" fails when the text is present
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command output should not contain "hello"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command output to not contain "hello", but it did.
      """

  @trait:CommandTrait
  Scenario: Assert that "the command output should be" fails on a mismatch
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command output should be "goodbye"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command output to be "goodbye", but got "hello".
      """

  @trait:CommandTrait
  Scenario: Assert that "the command error output should contain" fails when the text is absent
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command error output should contain "missing"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command error output to contain "missing", but it did not.
      """

  @trait:CommandTrait
  Scenario: Assert that "the command should complete in less than" fails when the command is slower
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "sleep 2"
      Then the command should complete in less than 1 second
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command to complete in less than 1 seconds, but it took
      """

  @trait:CommandTrait
  Scenario: Assert that "the command should complete in more than" fails when the command is faster
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo fast"
      Then the command should complete in more than 5 seconds
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the command to complete in more than 5 seconds, but it took
      """

  @trait:CommandTrait
  Scenario: Assert that an assertion before any command fails with a runtime exception
    Given some behat configuration
    And scenario steps:
      """
      Then the command should succeed
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No command has been run. Run a command before asserting on its result.
      """

  @trait:CommandTrait
  Scenario: Assert that a non-numeric exit code argument fails with a runtime exception
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command exit code should be "three"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The expected exit code must be numeric, but got "three".
      """

  @trait:CommandTrait
  Scenario: Assert that a non-numeric duration argument fails with a runtime exception
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo hello"
      Then the command should complete in less than "three" seconds
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The expected duration must be numeric, but got "three".
      """
