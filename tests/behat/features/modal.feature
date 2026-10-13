Feature: Check that ModalTrait works
  As Behat Steps library developer
  I want to provide tools to interact with and assert modals
  So that users can test modal-driven workflows

  @javascript @phpserver
  Scenario: Assert jQuery UI modal full lifecycle
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_jquery_ui.html"
    Then I should not see the modal
    When I click on the element "#open-settings"
    And I wait for the modal to appear
    Then I should see the modal
    And I should see the text "Settings modal content" in the modal
    And I should not see the text "Confirmation modal content" in the modal
    When I close the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with CSS selector
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_jquery_ui.html"
    And I click on the element "#open-settings"
    And I wait for the modal to appear
    Then I should see the modal
    When I click on the element ".btn-save" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with button text
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_jquery_ui.html"
    And I click on the element "#open-settings"
    And I wait for the modal to appear
    When I click on the element "Save" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI modal click with link text
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_jquery_ui.html"
    And I click on the element "#open-settings"
    And I wait for the modal to appear
    When I click on the element "Cancel" in the modal

  @javascript @phpserver
  Scenario: Assert jQuery UI second modal has different content
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_jquery_ui.html"
    And I click on the element "#open-confirm"
    And I wait for the modal to appear
    Then I should see the modal
    And I should see the text "Confirmation modal content" in the modal
    And I should not see the text "Settings modal content" in the modal

  @javascript @phpserver
  Scenario: Assert native dialog full lifecycle
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_native.html"
    Then I should not see the modal
    When I click on the element "#open-info"
    And I wait for the modal to appear
    Then I should see the modal
    And I should see the text "Info modal content" in the modal
    And I should not see the text "Delete modal content" in the modal
    When I click on the element "Close" in the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert native dialog click with button text
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_native.html"
    And I click on the element "#open-info"
    And I wait for the modal to appear
    When I click on the element "OK" in the modal

  @javascript @phpserver
  Scenario: Assert native dialog click with link text
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_native.html"
    And I click on the element "#open-info"
    And I wait for the modal to appear
    When I click on the element "View details" in the modal

  @javascript @phpserver
  Scenario: Assert native dialog second modal has different content
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_native.html"
    And I click on the element "#open-delete"
    And I wait for the modal to appear
    Then I should see the text "Delete modal content" in the modal
    And I should not see the text "Info modal content" in the modal

  @javascript @phpserver
  Scenario: Assert custom modal full lifecycle
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_custom.html"
    Then I should not see the modal
    When I click on the element "#open-profile"
    And I wait for the modal to appear
    Then I should see the modal
    And I should see the text "Profile modal content" in the modal
    And I should not see the text "Export modal content" in the modal
    When I close the modal
    Then I should not see the modal

  @javascript @phpserver
  Scenario: Assert custom modal click with CSS selector
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_custom.html"
    And I click on the element "#open-profile"
    And I wait for the modal to appear
    When I click on the element ".btn-update" in the modal

  @javascript @phpserver
  Scenario: Assert custom modal click with link text
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_custom.html"
    And I click on the element "#open-profile"
    And I wait for the modal to appear
    When I click on the element "Reset" in the modal

  @javascript @phpserver
  Scenario: Assert custom modal second modal has different content
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_custom.html"
    And I click on the element "#open-export"
    And I wait for the modal to appear
    Then I should see the text "Export modal content" in the modal
    And I should not see the text "Profile modal content" in the modal

  @javascript @phpserver
  Scenario: Assert a visible modal is found when an earlier selector matches a hidden one
    Given I am an anonymous user
    When I visit "http://cli:8888/modal_mixed.html"
    Then I should not see the modal
    When I click on the element "#open-native"
    And I wait for the modal to appear
    Then I should see the modal
    And I should see the text "Native modal content" in the modal
    And I should not see the text "Leftover jQuery UI modal content" in the modal

  @test-trait:ModalTrait
  Scenario: Assert "Then I should see the modal" fails when no modal is visible
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_jquery_ui.html"
      Then I should see the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @test-trait:ModalTrait
  Scenario: Assert "When I close the modal" fails when modal is hidden
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_jquery_ui.html"
      When I close the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @test-trait:ModalTrait
  Scenario: Assert "When I click :selector in the modal" fails when modal is hidden
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_jquery_ui.html"
      When I click on the element "Save" in the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @test-trait:ModalTrait
  Scenario: Assert "Then I should see the text :text in the modal" fails when modal is hidden
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_jquery_ui.html"
      Then I should see the text "some text" in the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal is not visible on the page.
      """

  @test-trait:ModalTrait
  Scenario: Assert "When I click :selector in the modal" fails when element not found
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_jquery_ui.html"
      When I press "Open Settings"
      When I click on the element ".nonexistent-element" in the modal
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element in the modal with css|id|name|title|alt|value|text ".nonexistent-element" not found.
      """

  @test-trait:ModalTrait
  Scenario: Assert "When I wait for the modal to appear" fails when modal does not appear within timeout
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_delayed.html"
      When I press "Open Delayed Modal"
      When I wait for the modal to appear
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The modal did not appear within 3 seconds.
      """

  @test-trait:ModalTrait
  Scenario: Assert "Then I should not see the modal" passes when no modal is visible
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_jquery_ui.html"
      Then I should not see the modal
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:ModalTrait
  Scenario: Assert that waiting for a modal fails naming the capability instead of spending the timeout
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/modal_jquery_ui.html"
      And I wait for the modal to appear
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\UnsupportedDriverActionException" exception:
      """
      No browser capability "DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface" is available for
      """
