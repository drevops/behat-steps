@d9 @d10
Feature: Check that FileTrait works for or D9

  @api
  Scenario: Assert "Given managed file:"
    When I am logged in as a user with the "administrator" role
    Given managed file:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    And "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists

  @api
  Scenario: Assert "@Given no managed files:"
    When I am logged in as a user with the "administrator" role
    Given managed file:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
    And "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists
    Given no managed files:
      | uri                          |
      | public://example_document.pdf |
      | public://example_image.png    |
      | public://example_audio.mp3    |
    Then no "example_document.pdf" file object exists
    And no "example_image.png" file object exists
    And no "example_audio.mp3" file object exists

  @api
  Scenario: Assert unmanaged files step definitions
    Given unmanaged file "public://test1.txt" does not exist
    When unmanaged file "public://test1.txt" created
    Then unmanaged file "public://test1.txt" exists
    Then unmanaged file "public://test2.txt" does not exist

    Given unmanaged file "public://test3.txt" does not exist
    When unmanaged file "public://test3.txt" created with content "test content"
    Then unmanaged file "public://test3.txt" exists
    And unmanaged file "public://test3.txt" has content "test content"
    And unmanaged file "public://test3.txt" has content "content"
    And unmanaged file "public://test3.txt" does not have content "test more content"

  @trait:FileTrait
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given unmanaged file "public://test4.txt" exists
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file public://test4.txt does not exist.
      """

  @trait:FileTrait
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given unmanaged file "public://test1.txt" created
      Then unmanaged file "public://test1.txt" does not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file public://test1.txt exists but it should not.
      """

  @trait:FileTrait
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given unmanaged file "public://test1.txt" created with content "test content"
      Then unmanaged file "public://test1.txt" exists
      And unmanaged file "public://test1.txt" has content "test other content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      File contents "test content" does not contain "test other content".
      """

  @trait:FileTrait
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given unmanaged file "public://test1.txt" created with content "test content"
      Then unmanaged file "public://test1.txt" exists
      And unmanaged file "public://test1.txt" does not have content "test content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      File contents "test content" contains "test content", but should not.
      """
