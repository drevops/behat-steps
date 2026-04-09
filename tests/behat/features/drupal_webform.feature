Feature: Check that WebformTrait works
  As Behat Steps library developer
  I want to provide tools to manage Drupal webforms programmatically
  So that users can test webform functionality reliably

  @api
  Scenario: Assert "@Given the webform :title does not exist" works as expected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/webform/add"
    And I fill in "Title" with "Test webform to delete"
    And I fill in "Machine-readable name" with "test_webform_to_delete"
    And I press "Save"
    And I visit "/admin/structure/webform"
    Then I should see "Test webform to delete"
    When the webform "Test webform to delete" does not exist
    And I visit "/admin/structure/webform"
    Then I should not see "Test webform to delete"

  @api
  Scenario: Assert "@Given the webform :title does not exist" works as expected on non-existing webform
    Given the webform "Non-existing webform" does not exist
