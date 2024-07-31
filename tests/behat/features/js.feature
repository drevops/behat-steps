@javascript
Feature: Check that JsTrait works

  Scenario: Assert javascript click on element
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    When I click on "#overlay-off-canvas-trigger" element

  Scenario: Assert javascript trigger event on element
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should not see an ".overlay-visible" element
    When I trigger JS "click" event on "#overlay-off-canvas-trigger" element
    Then I should see an ".overlay-visible" element

  Scenario: Assert javascript Accept/Not Accept confirmation
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should see the button "Test confirm"
    Then I should not see the button "You pressed OK!"
    Then I accept confirmation dialogs
    Then I press the "Test confirm" button
    Then I should see the button "You pressed OK!"

  Scenario: Assert javascript Not Accept confirmation
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should see the button "Test confirm"
    Then I should not see the button "You canceled!"
    Then I do not accept confirmation dialogs
    Then I press the "Test confirm" button
    Then I should see the button "You canceled!"

  Scenario: Assert scroll to an element with ID.
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I scroll to an element with id "main-inner"
    Then the element with id "main-inner" should be at the top of the page
