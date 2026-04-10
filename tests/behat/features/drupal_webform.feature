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

  @api @module:webform_templates
  Scenario: Assert "@Given I clone webform template :template into :title" works as expected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/webform/add"
    And I fill in "Title" with "Test template form"
    And I fill in "Machine-readable name" with "test_template_form"
    And I press "Save"
    And I visit "/admin/structure/webform/manage/test_template_form/settings"
    And I check "Allow this webform to be used as a template"
    And I press "Save"
    When I clone webform template "Test template form" into "Cloned contact form"
    And I visit "/admin/structure/webform"
    Then I should see "Cloned contact form"
    # Clean up the template.
    When the webform "Test template form" does not exist

  @trait:Drupal\WebformTrait
  Scenario: Assert "@Given I clone webform template :template into :title" fails for non-existing template
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I clone webform template "Non-existing template" into "My form"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No webform template matching "Non-existing template" was found.
      """
