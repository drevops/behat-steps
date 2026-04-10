Feature: Check that ModalTrait works
  As Behat Steps library developer
  I want to provide tools to interact with and assert modals
  So that users can test modal-driven workflows

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then I should see the modal" fails when no modal is visible
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      Then I should see the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I close the modal" fails when no visible modal is found
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      When I close the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I click :selector in the modal" fails when no visible modal is found
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      When I click "Save" in the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then the modal should contain :text" fails when no visible modal is found
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      Then the modal should contain "some text"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I close the modal" fails when modal is hidden
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_hidden.html"
      When I close the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I click :selector in the modal" fails when modal is hidden
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_hidden.html"
      When I click "Save" in the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """
