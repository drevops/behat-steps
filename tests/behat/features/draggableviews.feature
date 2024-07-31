@api
Feature: Check that DraggableViewsTrait works

  Scenario: Assert save order of the Draggable Order items
    Given I am logged in as a user with the "administrator" role
    Then "draggableviews_demo" content:
      | title  | status | created           |
      | Test 1 | 1      | 2014-10-17 8:00am |
      | Test 2 | 1      | 2014-10-17 9:00am |
    Then I visit "/draggableviews-demo"
    Then I save screenshot
    Then I should see an element ".view-draggableviews-demo .views-row:first-child .views-field-title" using "css" contains "Test 2" text
    Then I should see an element ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" using "css" contains "Test 1" text
    Then I save draggable views "draggableviews_demo" view "draggableviews_demo_order" display "draggableviews_demo" items in the following order:
      | Test 1 |
      | Test 2 |
    # We should not need clear cache at here. Re-check later.
    Then I visit "/admin/config/development/performance"
    Then I press the "Clear all cache" button
    Then I visit "/draggableviews-demo"
    Then I should see an element ".view-draggableviews-demo .views-row:first-child .views-field-title" using "css" contains "Test 1" text
    Then I should see an element ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" using "css" contains "Test 2" text

    Then I save draggable views "draggableviews_demo" view "draggableviews_demo_order" display "draggableviews_demo" items in the following order:
      | Test 2 |
      | Test 1 |
    # Clear cache again.
    Then I visit "/admin/config/development/performance"
    Then I press the "Clear all cache" button
    Then I visit "/draggableviews-demo"
    Then I should see an element ".view-draggableviews-demo .views-row:first-child .views-field-title" using "css" contains "Test 2" text
    Then I should see an element ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" using "css" contains "Test 1" text

  @trait:DraggableViewsTrait
  Scenario: Assert save order of the Draggable Order items throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given I save draggable views "draggableviews_demo" view "draggableviews_demo_order" display "draggableviews_demo" items in the following order:
        | Test 1 |
        | Test 2 |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find node "Test 1"
      """
