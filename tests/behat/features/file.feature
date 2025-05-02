Feature: Check that FileTrait works
  As Behat Steps library developer
  I want to provide tools to manage files programmatically
  So that users can test file functionality and operations

  @api
  Scenario: Assert "When the following managed files:"
    Given I am logged in as a user with the "administrator" role
    When the following managed files:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    And the following managed files:
      | uuid                                 | path             |
      | 9cb1b484-db7b-4496-bd63-8c702e207704 | example_text.txt |
    Then "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists
    And "example_text.txt" file object exists
    And "file" entity exists with UUID "9cb1b484-db7b-4496-bd63-8c702e207704"

  @api
  Scenario: Assert "When the following managed files: With uri"
    Given I am logged in as a user with the "administrator" role
    And no "example_document.pdf" file object exists
    And no "example_image.png" file object exists
    And no "example_audio.mp3" file object exists
    When the following managed files:
      | path                 | uri                                |
      | example_document.pdf | public://test/example_document.pdf |
      | example_image.png    | public://test/example_image.png    |
      | example_audio.mp3    | public://test/example_audio.mp3    |
    Then "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists

  @api
  Scenario: Assert "When the following managed files do not exist: With filename"
    Given I am logged in as a user with the "administrator" role
    When the following managed files:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    Then "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists
    When the following managed files do not exist:
      | filename             |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    Then no "example_document.pdf" file object exists
    And no "example_image.png" file object exists
    And no "example_audio.mp3" file object exists

  @api
  Scenario: Assert "When the following managed files do not exist: With uri"
    Given I am logged in as a user with the "administrator" role
    When the following managed files:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    Then "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists
    When the following managed files do not exist:
      | uri                           |
      | public://example_document.pdf |
      | public://example_image.png    |
      | public://example_audio.mp3    |
    Then no "example_document.pdf" file object exists
    And no "example_image.png" file object exists
    And no "example_audio.mp3" file object exists

  @api
  Scenario: Assert "When the following managed files do not exist: With status"
    Given I am logged in as a user with the "administrator" role
    When the following managed files:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    Then "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists
    When the following managed files do not exist:
      | status |
      | 1      |
    Then no "example_document.pdf" file object exists
    And no "example_image.png" file object exists
    And no "example_audio.mp3" file object exists

  @api
  Scenario: Assert "When the following managed files do not exist: With filemime"
    Given I am logged in as a user with the "administrator" role
    When the following managed files:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    Then "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists
    When the following managed files do not exist:
      | filemime  |
      | image/png |
    Then "example_document.pdf" file object exists
    And no "example_image.png" file object exists
    And "example_audio.mp3" file object exists

  @api
  Scenario: Assert unmanaged files step definitions
    Given an unmanaged file at the URI "public://test1.txt" should not exist
    When the unmanaged file at the URI "public://test1.txt" exists
    Then an unmanaged file at the URI "public://test1.txt" should exist
    And an unmanaged file at the URI "public://test2.txt" should not exist

    Given an unmanaged file at the URI "public://test3.txt" should not exist
    When the unmanaged file at the URI "public://test3.txt" exists with "test content"
    Then an unmanaged file at the URI "public://test3.txt" should exist
    And an unmanaged file at the URI "public://test3.txt" should contain "test content"
    And an unmanaged file at the URI "public://test3.txt" should contain "content"
    And an unmanaged file at the URI "public://test3.txt" should not contain "test more content"

    Given an unmanaged file at the URI "public://test-random/test4.txt" should not exist
    When the unmanaged file at the URI "public://test-random/test4.txt" exists with "test content"
    Then an unmanaged file at the URI "public://test-random/test4.txt" should exist

  @trait:FileTrait
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given an unmanaged file at the URI "public://test4.txt" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file public://test4.txt does not exist.
      """

  @trait:FileTrait
  Scenario: Assert that negative assertion for "Then an unmanaged file at the URI :uri should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the unmanaged file at the URI "public://test1.txt" exists
      Then an unmanaged file at the URI "public://test1.txt" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file public://test1.txt exists but it should not.
      """

  @trait:FileTrait
  Scenario: Assert that negative assertion for "Then an unmanaged file at the URI :uri should contain :content" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the unmanaged file at the URI "public://test1.txt" exists with "test content"
      Then an unmanaged file at the URI "public://test1.txt" should exist
      And an unmanaged file at the URI "public://test1.txt" should contain "test other content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      File contents "test content" does not contain "test other content".
      """

  @trait:FileTrait
  Scenario: Assert that negative assertion for "Then an unmanaged file at the URI :uri should not contain :content" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the unmanaged file at the URI "public://test1.txt" exists with "test content"
      Then an unmanaged file at the URI "public://test1.txt" should exist
      And an unmanaged file at the URI "public://test1.txt" should not contain "test content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      File contents "test content" contains "test content", but should not.
      """
