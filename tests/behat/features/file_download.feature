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

  @api @download
  Scenario: Assert "When I download the file from the link :link"
    When I visit the "article" content page with the title "[TEST] document page"
    When I download the file from the link "example_text.txt"
    Then the downloaded file should contain:
      """
      Some Text
      """
    And the downloaded file should contain:
      """
      /Some/i
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that regex content match fails properly
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I visit "/"
      And I download the file from the URL "/example_text.txt"
      Then the downloaded file should contain:
        '''
        /nonexistent.*pattern/
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find a content line with searched string
      """

  @api @download
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

  @api @download
  Scenario: Assert the downloaded file name contains a specific string
    When I download the file from the URL "/example_text.txt"
    Then the downloaded file name should contain "example"

  @api @trait:FileDownloadTrait
  Scenario: Assert that negative assertion for "The downloaded file name should contain :name" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
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

  @api @download
  Scenario: Assert the downloaded file should be a zip archive containing the files partially named
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "example_files.zip"
    Then the downloaded file name should be "example_files.zip"
    And the downloaded file should be a zip archive containing the files partially named:
      | example_aud |
      | example_ima |

  @api @trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that negative assertion for "the downloaded file should be a zip archive containing the files partially named" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
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

  @api @download
  Scenario: Assert the downloaded file is a zip archive not containing files partially named
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "example_files.zip"
    Then the downloaded file name should be "example_files.zip"
    And the downloaded file should be a zip archive not containing the files partially named:
      | example_text |
      | not_existing |

  @api @trait:FileDownloadTrait
  Scenario: Assert that downloading from missing link fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      And I download the file from the link "nonexistent_link"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No link "nonexistent_link" is present on the page, but expected to be present
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that file name mismatch fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I visit "/"
      And I download the file from the URL "/example_text.txt"
      Then the downloaded file name should be "wrong_name.txt"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file "example_text.txt", but expected "wrong_name.txt"
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that file content not found fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I visit "/"
      And I download the file from the URL "/example_text.txt"
      Then the downloaded file should contain:
        '''
        nonexistent content string
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find a content line with searched string
      """

  @api @trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that zip archive with missing files fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "article" content page with the title "[TEST] zip page"
      When I download the file from the link "example_files.zip"
      Then the downloaded file should be a zip archive containing the files named:
        | nonexistent1.txt |
        | nonexistent2.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find file "nonexistent1.txt" in archive
      """

  @api @trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that zip archive with found excluded files fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "article" content page with the title "[TEST] zip page"
      When I download the file from the link "example_files.zip"
      Then the downloaded file should be a zip archive not containing the files partially named:
        | example_audio |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Found file partially named "example_audio" in archive but should not
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that skip tag for beforeScenario hook works
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:fileDownloadBeforeScenario":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @api @trait:FileDownloadTrait
  Scenario: Assert that skip tag for afterScenario hook works
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:fileDownloadAfterScenario":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @api @trait:FileDownloadTrait
  Scenario: Assert that checking file name without download fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the downloaded file name should be "test.txt"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Downloaded file name content has no data.
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that checking file name contains without download fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the downloaded file name should contain "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Downloaded file name content has no data.
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that checking file content without download fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the downloaded file should contain:
        '''
        Some content
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Downloaded file content has no data.
      """

  @api @trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that invalid ZIP file fails with an error
    Given some behat configuration
    And the following managed files:
      | path        |
      | invalid.zip |
    And scenario steps tagged with "@download":
      """
      Given I am logged in as a user with the "administrator" role
      When I download the file from the URL "/invalid.zip"
      Then the downloaded file should be a zip archive containing the files named:
        | test.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file is not a valid ZIP file.
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that ZIP assertion without download fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the downloaded file should be a zip archive containing the files named:
        | test.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Downloaded file path data is not available.
      """

  @api @trait:FileDownloadTrait
  Scenario: Assert that ZIP assertion on non-ZIP file fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I visit "/"
      And I download the file from the URL "/example_text.txt"
      Then the downloaded file should be a zip archive containing the files named:
        | test.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file does not have correct headers set for ZIP.
      """
