@api
Feature: Check that DraggableViewsTrait works

  Scenario: Assert save order of the Draggable Order items
    Given "draggableviews_demo" content:
      | title  | status | created           |
      | Test 1 | 1      | 2014-10-17 8:00am |
      | Test 2 | 1      | 2014-10-17 9:00am |
    And I am logged in as a user with the "administrator" role

    When I visit "/draggableviews-demo"
    And I save screenshot
    Then the ".view-draggableviews-demo .views-row:first-child .views-field-title" element should contain "Test 2"
    And the ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" element should contain "Test 1"

    When I save the draggable views items of the view "draggableviews_demo" and the display "draggableviews_demo_order" for the "draggableviews_demo" content in the following order:
      | Test 1 |
      | Test 2 |
    And the cache has been cleared
    And I visit "/draggableviews-demo"
    Then the ".view-draggableviews-demo .views-row:first-child .views-field-title" element should contain "Test 1"
    And the ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" element should contain "Test 2"

    When I save the draggable views items of the view "draggableviews_demo" and the display "draggableviews_demo_order" for the "draggableviews_demo" content in the following order:
      | Test 2 |
      | Test 1 |
    And the cache has been cleared
    And I visit "/draggableviews-demo"
    Then the ".view-draggableviews-demo .views-row:first-child .views-field-title" element should contain "Test 2"
    And the ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" element should contain "Test 1"

  @trait:DraggableViewsTrait
  Scenario: Assert that negative assertion for "When I save the draggable views items of the view :view_id and the display :views_display_id for the :bundle content in the following order:" step throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given "draggableviews_demo" content:
        | title  | status | created           |
        | Test 1 | 1      | 2014-10-17 8:00am |
        | Test 2 | 1      | 2014-10-17 9:00am |
      And I am logged in as a user with the "administrator" role
      When I save the draggable views items of the view "draggableviews_demo" and the display "draggableviews_demo_order" for the "draggableviews_demo" content in the following order:
        | Test 1 |
        | Test 2 |
        | Test 3 |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the node "Test 3"
      """
