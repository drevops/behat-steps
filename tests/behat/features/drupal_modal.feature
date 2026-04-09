Feature: Check that ModalTrait works
  As Behat Steps library developer
  I want to provide tools to interact with and assert Drupal modal dialogs
  So that users can test modal-driven workflows

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then I should see the modal dialog" fails when no modal is visible
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      Then I should see the modal dialog
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal dialog is not visible on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I close the modal dialog" fails when no modal dialog is found
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      When I close the modal dialog
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal dialog was not found on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I click :button in the modal dialog" fails when no modal is found
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      When I click "Save" in the modal dialog
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal dialog was not found on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then the modal dialog should contain :text" fails when no modal content found
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      Then the modal dialog should contain "some text"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal dialog content element was not found on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I close the modal dialog" fails when modal is hidden
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_hidden.html"
      When I close the modal dialog
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal dialog was not found on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I click :button in the modal dialog" fails when modal is hidden
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_hidden.html"
      When I click "Save" in the modal dialog
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal dialog was not found on the page.
      """
