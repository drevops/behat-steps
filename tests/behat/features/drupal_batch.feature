Feature: Check that BatchTrait works
  As Behat Steps library developer
  I want to provide a step that waits for Drupal's Batch API
  So that assertions run against the finished operation, not the progress page

  @api @javascript
  Scenario: Assert "When I wait for the batch job to finish" returns on a page with no batch
    Given the user is anonymous
    When I visit "/"
    And I wait for the batch job to finish
    Then the response status code should be 200
