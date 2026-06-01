Feature: Check that DropzoneTrait works
  As Behat Steps library developer
  I want to provide a tool to simulate multi-file drag-and-drop
  So that users can test concurrent file-drop pipelines

  @javascript @phpserver
  Scenario: Assert single-file drop on default selector
    Given I am an anonymous user
    When I visit "/sites/default/files/dropzone.html"
    And I drop the file "document.pdf" on the ".dropzone" dropzone
    Then I should see "document.pdf"
    And the "#event-count" element should contain "1"

  @javascript @phpserver
  Scenario: Assert multi-file drop fires a single drop event
    Given I am an anonymous user
    When I visit "/sites/default/files/dropzone.html"
    And I drop the following files on the ".dropzone" dropzone:
      | document.pdf |
      | image.png    |
      | text.txt     |
    Then I should see "document.pdf"
    And I should see "image.png"
    And I should see "text.txt"
    And the "#event-count" element should contain "1"

  @javascript @phpserver
  Scenario: Assert drop on a non-default selector
    Given I am an anonymous user
    When I visit "/sites/default/files/dropzone.html"
    And I drop the file "image.png" on the "#secondary-zone" dropzone
    Then the "#secondary-output" element should contain "image.png"
    And the "#primary-output" element should not contain "image.png"

  @javascript @phpserver
  Scenario: Assert two consecutive drops in one scenario do not collide
    Given I am an anonymous user
    When I visit "/sites/default/files/dropzone.html"
    And I drop the file "document.pdf" on the ".dropzone" dropzone
    And I drop the file "text.txt" on the ".dropzone" dropzone
    Then I should see "document.pdf"
    And I should see "text.txt"
    And the "#event-count" element should contain "2"

  # Negative tests.

  @trait:DropzoneTrait
  Scenario: Assert "When I drop the file ..." fails when target element is missing
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/dropzone.html"
      And I drop the file "document.pdf" on the ".nonexistent-zone" dropzone
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css ".nonexistent-zone" not found.
      """

  @trait:DropzoneTrait
  Scenario: Assert "When I drop the file ..." fails when fixture file is missing
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/dropzone.html"
      And I drop the file "missing-fixture.bin" on the ".dropzone" dropzone
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      missing-fixture.bin" does not exist.
      """

  @trait:DropzoneTrait
  Scenario: Assert "When I drop the following files ..." fails when fixture file is missing
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/dropzone.html"
      And I drop the following files on the ".dropzone" dropzone:
        | document.pdf       |
        | missing-second.bin |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      missing-second.bin" does not exist.
      """
