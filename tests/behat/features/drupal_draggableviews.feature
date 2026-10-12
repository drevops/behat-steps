Feature: Check that DraggableviewsTrait works
  As Behat Steps library developer
  I want to provide tools to manage the order of items in draggable views
  So that users can test content ordering functionality

  Scenario: Assert save order of the Draggable Order items
    Given the following "draggableviews_demo" content exists:
      | title  | status | created           |
      | Test 1 | 1      | 2014-10-17 8:00am |
      | Test 2 | 1      | 2014-10-17 9:00am |
    And I am logged in as a user with the "administrator" role

    When I visit "/draggableviews-demo"
    And I save screenshot
    Then the ".view-draggableviews-demo .views-row:first-child .views-field-title" element should contain "Test 2"
    And the ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" element should contain "Test 1"

    When the draggable views items of the "draggableviews_demo" view and the "draggableviews_demo_order" display for the "draggableviews_demo" content are saved in the following order:
      | Test 1 |
      | Test 2 |
    And the cache has been cleared
    And I visit "/draggableviews-demo"
    Then the ".view-draggableviews-demo .views-row:first-child .views-field-title" element should contain "Test 1"
    And the ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" element should contain "Test 2"

    When the draggable views items of the "draggableviews_demo" view and the "draggableviews_demo_order" display for the "draggableviews_demo" content are saved in the following order:
      | Test 2 |
      | Test 1 |
    And the cache has been cleared
    And I visit "/draggableviews-demo"
    Then the ".view-draggableviews-demo .views-row:first-child .views-field-title" element should contain "Test 2"
    And the ".view-draggableviews-demo .views-row:nth-child(2) .views-field-title" element should contain "Test 1"

  @test-trait:Drupal\DraggableviewsTrait
  Scenario: Assert that negative assertion for "When the draggable views items of the :view_id view and the :view_display_id display for the :content_type content are saved in the following order:" step throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given the following "draggableviews_demo" content exists:
        | title  | status | created           |
        | Test 1 | 1      | 2014-10-17 8:00am |
        | Test 2 | 1      | 2014-10-17 9:00am |
      And I am logged in as a user with the "administrator" role
      When the draggable views items of the "draggableviews_demo" view and the "draggableviews_demo_order" display for the "draggableviews_demo" content are saved in the following order:
        | Test 1 |
        | Test 2 |
        | Test 3 |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the node "Test 3"
      """
