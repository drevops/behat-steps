@d7 @d8
Feature: Check that PathTrait works

  Scenario: User is at the path without prefixed slash.
    Given I go to "user/login"
    Then I should be in the "user/login" path

  Scenario: User is at the path with prefixed slash
    Given I go to "/user/login"
    Then I should be in the "/user/login" path

  Scenario: User is at the '<front>' path.
    Given I go to "/"
    Then I should be in the "<front>" path
