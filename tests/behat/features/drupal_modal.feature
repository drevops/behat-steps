Feature: Check that ModalTrait works
  As Behat Steps library developer
  I want to provide tools to interact with and assert modals
  So that users can test modal-driven workflows

  # jQuery UI modal tests.

  @javascript @phpserver
  Scenario: Assert jQuery UI modal visibility and content
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    Then I should see the modal
    And the modal should contain "jQuery UI modal content"
    And the modal should not contain "nonexistent text"

  @javascript @phpserver
  Scenario: Assert jQuery UI modal close button
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    Then I should see the modal
    When I close the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with CSS selector
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    Then I should see the modal
    When I click ".btn-save" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with button text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    Then I should see the modal
    When I click "Save" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with link text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    Then I should see the modal
    When I click "Cancel" in the modal

  # Native dialog element tests.

  @javascript @phpserver
  Scenario: Assert native dialog visibility and content
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_native.html"
    Then I should see the modal
    And the modal should contain "Native dialog content"
    And the modal should not contain "nonexistent text"

  @javascript @phpserver
  Scenario: Assert native dialog click with button text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_native.html"
    Then I should see the modal
    When I click "Confirm" in the modal

  @javascript @phpserver
  Scenario: Assert native dialog click with link text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_native.html"
    Then I should see the modal
    When I click "View details" in the modal

  # Custom CSS modal tests.

  @javascript @phpserver
  Scenario: Assert custom modal visibility and content
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    Then I should see the modal
    And the modal should contain "Custom modal content"
    And the modal should not contain "nonexistent text"

  @javascript @phpserver
  Scenario: Assert custom modal close button
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    Then I should see the modal
    When I close the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert custom modal click with CSS selector
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    Then I should see the modal
    When I click ".btn-submit" in the modal

  @javascript @phpserver
  Scenario: Assert custom modal click with link text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    Then I should see the modal
    When I click "Help" in the modal

  # Negative tests.

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then I should see the modal" fails when no modal is visible
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_hidden.html"
      Then I should see the modal
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

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then the modal should contain :text" fails when modal is hidden
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_hidden.html"
      Then the modal should contain "some text"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "When I click :selector in the modal" fails when element not found
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_jquery_ui.html"
      When I click ".nonexistent-element" in the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The element ".nonexistent-element" was not found in the modal.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then I should not see the modal" passes when no modal exists
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "/admin/content"
      Then I should not see the modal
      """
    When I run "behat --no-colors"
    Then it should pass
