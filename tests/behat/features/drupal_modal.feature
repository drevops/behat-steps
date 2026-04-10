Feature: Check that ModalTrait works
  As Behat Steps library developer
  I want to provide tools to interact with and assert modals
  So that users can test modal-driven workflows

  # jQuery UI modal tests.

  @javascript @phpserver
  Scenario: Assert jQuery UI modal full lifecycle
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    Then I should not see the modal
    When I click on the element "#open-settings"
    And I wait for the modal to appear
    Then I should see the modal
    And the modal should contain "Settings modal content"
    And the modal should not contain "Confirmation modal content"
    When I close the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with CSS selector
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    And I click on the element "#open-settings"
    And I wait for the modal to appear
    Then I should see the modal
    When I click ".btn-save" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with button text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    And I click on the element "#open-settings"
    And I wait for the modal to appear
    When I click "Save" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with link text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    And I click on the element "#open-settings"
    And I wait for the modal to appear
    When I click "Cancel" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI second modal has different content
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_jquery_ui.html"
    And I click on the element "#open-confirm"
    And I wait for the modal to appear
    Then I should see the modal
    And the modal should contain "Confirmation modal content"
    And the modal should not contain "Settings modal content"

  # Native dialog element tests.

  @javascript @phpserver
  Scenario: Assert native dialog full lifecycle
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_native.html"
    Then I should not see the modal
    When I click on the element "#open-info"
    And I wait for the modal to appear
    Then I should see the modal
    And the modal should contain "Info modal content"
    And the modal should not contain "Delete modal content"
    When I click "Close" in the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert native dialog click with button text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_native.html"
    And I click on the element "#open-info"
    And I wait for the modal to appear
    When I click "OK" in the modal

  @javascript @phpserver
  Scenario: Assert native dialog click with link text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_native.html"
    And I click on the element "#open-info"
    And I wait for the modal to appear
    When I click "View details" in the modal

  @javascript @phpserver
  Scenario: Assert native dialog second modal has different content
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_native.html"
    And I click on the element "#open-delete"
    And I wait for the modal to appear
    Then the modal should contain "Delete modal content"
    And the modal should not contain "Info modal content"

  # Custom CSS modal tests.

  @javascript @phpserver
  Scenario: Assert custom modal full lifecycle
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    Then I should not see the modal
    When I click on the element "#open-profile"
    And I wait for the modal to appear
    Then I should see the modal
    And the modal should contain "Profile modal content"
    And the modal should not contain "Export modal content"
    When I close the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert custom modal click with CSS selector
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    And I click on the element "#open-profile"
    And I wait for the modal to appear
    When I click ".btn-update" in the modal

  @javascript @phpserver
  Scenario: Assert custom modal click with link text
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    And I click on the element "#open-profile"
    And I wait for the modal to appear
    When I click "Reset" in the modal

  @javascript @phpserver
  Scenario: Assert custom modal second modal has different content
    Given I am an anonymous user
    When I visit "/sites/default/files/modal_custom.html"
    And I click on the element "#open-export"
    And I wait for the modal to appear
    Then the modal should contain "Export modal content"
    And the modal should not contain "Profile modal content"

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
      When I click on the element "#open-settings"
      When I click ".nonexistent-element" in the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The element ".nonexistent-element" was not found in the modal.
      """

  @trait:Drupal\ModalTrait
  Scenario: Assert "Then I should not see the modal" passes when no modal is visible
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/modal_hidden.html"
      Then I should not see the modal
      """
    When I run "behat --no-colors"
    Then it should pass
