@d9
Feature: Check that ElementTrait works

  @api
  Scenario: Assert step definition "Then I should see the :selector element with the :attribute attribute set to :value" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then I should see the "html" element with the "dir" attribute set to "ltr"
