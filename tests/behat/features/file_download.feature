Feature: Check that FileDownloadTrait works
  As Behat Steps library developer
  I want to provide tools to test file download functionality
  So that users can verify file downloads work correctly

  Background:
    Given I am logged in as a user with the "administrator" role
    When the following managed files:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
      | example_text.txt     |
      | example_files.zip    |
    And article content:
      | title                | field_file        |
      | [TEST] document page | example_text.txt  |
      | [TEST] zip page      | example_files.zip |

  @api @download
  Scenario: Assert "When I download the file from the URL :url"
    When I download the file from the URL "/example_text.txt"

  @api @javascript @download
  Scenario: Assert in browser "When I download the file from the URL :url"
    When I download the file from the URL "/example_text.txt"

  @api
  Scenario: Assert "When I download the file from the link :link"
    When I visit the "article" content page with the title "[TEST] document page"
    When I download the file from the link "example_text.txt"
    Then the downloaded file should contain:
      """
      Some Text
      """

  @api
  Scenario: Assert "Given downloaded file is zip archive that contains files:"
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "example_files.zip"
    Then the downloaded file name should be "example_files.zip"
    And the downloaded file should be a zip archive containing the files named:
      | example_audio.mp3    |
      | example_image.png    |
      | example_document.pdf |
    And the downloaded file should be a zip archive not containing the files partially named:
      | example_text.txt |
      | not_existing.png |

  @api
  Scenario: Assert the downloaded file name contains a specific string
    When I download the file from the URL "/example_text.txt"
    Then the downloaded file name should contain "example"

  @api @trait:FileDownloadTrait
  Scenario: Assert that negative assertion for "The downloaded file name should contain :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      And I download the file from the URL "/example_text.txt"
      Then the downloaded file name should contain "nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file name "example_text.txt" does not contain "nonexistent"
      """

  @api
  Scenario: Assert the downloaded file should be a zip archive containing the files partially named
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "example_files.zip"
    Then the downloaded file name should be "example_files.zip"
    And the downloaded file should be a zip archive containing the files partially named:
      | example_aud |
      | example_ima |

  @api @trait:FileDownloadTrait,ContentTrait
  Scenario: Assert that negative assertion for "the downloaded file should be a zip archive containing the files partially named" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "article" content page with the title "[TEST] zip page"
      When I download the file from the link "example_files.zip"
      And the downloaded file name should be "example_files.zip"
      Then the downloaded file should be a zip archive containing the files partially named:
        | nonexistent_file |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find any file partially named "nonexistent_file" in archive
      """

  @api
  Scenario: Assert the downloaded file is a zip archive not containing files partially named
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "example_files.zip"
    Then the downloaded file name should be "example_files.zip"
    And the downloaded file should be a zip archive not containing the files partially named:
      | example_text |
      | not_existing |
