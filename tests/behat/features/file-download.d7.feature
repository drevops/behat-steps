@d7
Feature: Check that FileDownloadTrait for D7 works

  Background:
    Given I am logged in as a user with the "administrator" role
    And article content:
      | title                | field_file                                                   |
      | [TEST] document page | file;example_files/example_document.txt;example_document.txt |
    And article content:
      | title           | field_file                                             |
      | [TEST] zip page | file;example_files/example_files.zip;example_files.zip |

  @api @download
  Scenario: Assert that a file can be downloaded
    And I download file from "/example_files/example_document.txt"

  @api
  Scenario: Assert that a file can be downloaded by existing link
    When I visit article "[TEST] document page"
    Then I see download "Download example_document.txt" link "present"
    Then I download file from link "Download example_document.txt"
    And downloaded file contains:
    """
    Some Text
    """

  @api
  Scenario: Assert that an archive file can be downloaded
    When I visit article "[TEST] zip page"
    Then I see download "Download example_files.zip" link "present"
    Then I download file from link "Download example_files.zip"
    And downloaded file name is "example_files.zip"
    And downloaded file is zip archive that contains files:
      | example_audio.mp3    |
      | example_image.png    |
      | example_document.pdf |
