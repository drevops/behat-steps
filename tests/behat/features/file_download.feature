Feature: Check that FileDownloadTrait works

  Background:
    Given I am logged in as a user with the "administrator" role
    Given managed file:
      | path                 |
      | example_document.pdf |
      | example_image.png    |
      | example_audio.mp3    |
      | example_text.txt     |
      | example text.txt     |
      | example_files.zip    |
    And article content:
      | title                | field_file        |
      | [TEST] document page | example_text.txt  |
      | [TEST] zip page      | example_files.zip |
      | [TEST] document page 2 | example text.txt |

  @api @download
  Scenario: Assert "Then I download file from :url"
    And I download file from "/example_text.txt"

  @api @javascript @download
  Scenario: Assert in browser "Then I download file from :url"
    And I download file from "/example_text.txt"

  @api
  Scenario: Assert "Then I download file from link :link"
    When I visit article "[TEST] document page"
    Then I see download "example_text.txt" link "present"
    And downloaded file name is "example_text.text"
    Then I download file from link "example_text.txt"
    And downloaded file contains:
      """
      Some Text
      """

  @api
  Scenario: Assert "Given downloaded file is zip archive that contains files:"
    When I visit article "[TEST] zip page"
    Then I see download "example_files.zip" link "present"
    Then I download file from link "example_files.zip"
    And downloaded file name is "example_files.zip"
    And downloaded file is zip archive that contains files:
      | example_audio.mp3    |
      | example_image.png    |
      | example_document.pdf |
    Then downloaded file is zip archive that does not contain files:
      | example_text.txt |
      | not_existing.png |

  @api
  Scenario: Assert file name with a space is correctly checked
    When I visit article "[TEST] document page 2"
    Then I see download "example text.txt" link "present"
    Then I download file from link "example text.txt"
    And downloaded file name is "example%20text.txt"
