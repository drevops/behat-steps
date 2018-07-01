@d7
Feature: Check that LinkTrait works

  Scenario: Assert link with href without locator
    Given I go to "/"
    Then I should see the link "Create new account" with "user/register"

  Scenario: Assert link with href with locator
    Given I go to "/"
    Then I should see the link "Create new account" with "user/register" in "#user-login-form"

