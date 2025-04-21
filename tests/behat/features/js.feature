Feature: Check that JsTrait works

  @javascript
  Scenario: Assert javascript click on element
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    When I click on the element "#overlay-off-canvas-trigger"

  @javascript
  Scenario: Assert javascript trigger event on element
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should not see an ".overlay-visible" element
    When I trigger the JS event "click" on the element "#overlay-off-canvas-trigger"
    Then I should see an ".overlay-visible" element

  @javascript
  Scenario: Assert javascript Accept/Not Accept confirmation
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should see the button "Test confirm"
    Then I should not see the button "You pressed OK!"
    Then I accept all confirmation dialogs
    Then I press the "Test confirm" button
    Then I should see the button "You pressed OK!"

  @javascript
  Scenario: Assert javascript Not Accept confirmation
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should see the button "Test confirm"
    Then I should not see the button "You canceled!"
    Then I do not accept any confirmation dialogs
    Then I press the "Test confirm" button
    Then I should see the button "You canceled!"

  @javascript
  Scenario: Assert scroll to an element with ID.
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    When I scroll to the element "#main-inner"
    Then the element "#main-inner" should be at the top of the viewport
