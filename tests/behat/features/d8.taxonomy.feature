@d8
Feature: Check that TaxonomyTrait works for D8

  Background:
    Given no "tags" terms:
      | T1 |
    And "tags" terms:
      | name |
      | T1   |

  @api
  Scenario: Assert "Given vocabulary :vid with name :name exists"
    Given I am logged in as a user with the "administrator" role
    And vocabulary tags with name "Tags" exists
    And taxonomy term "T1" from vocabulary "tags" exists
    When I visit "tags" vocabulary term "T1"
    Then I see the text "T1"

    When I edit "tags" vocabulary term "T1"
    Then I see the text "Edit term"
    And I see the text "T1"
