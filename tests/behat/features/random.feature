Feature: Check that RandomTrait works
  As Behat Steps library developer
  I want to provide tools to generate random values inside step arguments
  So that users can write scenarios that do not collide on fixed values

  @phpserver
  Scenario: Assert that a token resolves to the same value throughout a scenario
    Given the user is anonymous
    When I visit "http://cli:8888/form1.html"
    And I fill in "username" with "[?title]"
    Then the "username" field should contain "[?title]"

  @phpserver
  Scenario: Assert that tokens of different names resolve to different values
    Given the user is anonymous
    When I visit "http://cli:8888/form1.html"
    And I fill in "username" with "[?first]"
    Then the "username" field should not contain "[?second]"

  @phpserver
  Scenario: Assert that a typed token resolves
    Given the user is anonymous
    When I visit "http://cli:8888/form1.html"
    And I fill in "username" with "[?mail:email]"
    Then the "username" field should contain "[?mail:email]"
