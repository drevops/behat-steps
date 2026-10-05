Feature: Check that FileDownloadTrait works
  As Behat Steps library developer
  I want to provide tools to test file download functionality
  So that users can verify file downloads work correctly

  Background:
    When I log in as a user with the role "administrator"
    When the following managed files exist:
      | path                 |
      | document.pdf         |
      | image.png            |
      | audio.mp3            |
      | text.txt             |
      | archive_multiple.zip |
    And the following article content exist:
      | title                | field_file           |
      | [TEST] document page | text.txt             |
      | [TEST] zip page      | archive_multiple.zip |

  @download @phpserver
  Scenario: Assert "When I download the file from the URL :url"
    When I download the file from the URL "http://cli:8888/text.txt"

  @javascript @download @phpserver
  Scenario: Assert in browser "When I download the file from the URL :url"
    When I download the file from the URL "http://cli:8888/text.txt"

  @download
  Scenario: Assert "When I download the file from the link :link"
    When I visit the "article" content page with the title "[TEST] document page"
    When I download the file from the link "text.txt"
    Then the downloaded file should contain:
      """
      Some Text
      """
    And the downloaded file should contain:
      """
      /Some/i
      """

  @download
  Scenario: Assert "When I download the file from the URL :url" sends the basic authentication a step set
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    When the basic authentication has the username "admin-test" and the password "admin-test"
    And I download the file from the URL "/mysite_core/test-basic-auth"
    Then the downloaded file should contain:
      """
      admin-test
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that a download fails without the basic authentication its route requires
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I download the file from the URL "/mysite_core/test-basic-auth"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      returned HTTP status 401
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that regex content match fails properly
    Given some behat configuration
    And scenario steps tagged with "@download @phpserver":
      """
      When I visit "/"
      And I download the file from the URL "http://cli:8888/text.txt"
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

  @download
  Scenario: Assert "Given downloaded file is zip archive that contains files:"
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "archive_multiple.zip"
    Then the downloaded file name should be "archive_multiple.zip"
    And the downloaded file should be a zip archive containing the following files named:
      | audio.mp3    |
      | image.png    |
      | document.pdf |
    And the downloaded file should be a zip archive not containing the following files partially named:
      | text.txt         |
      | not_existing.png |

  @download @phpserver
  Scenario: Assert the downloaded file name contains a specific string
    When I download the file from the URL "http://cli:8888/text.txt"
    Then the downloaded file name should contain "text"

  @test-trait:FileDownloadTrait
  Scenario: Assert that negative assertion for "The downloaded file name should contain :name" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download @phpserver":
      """
      When I visit "/"
      And I download the file from the URL "http://cli:8888/text.txt"
      Then the downloaded file name should contain "nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file name "text.txt" does not contain "nonexistent"
      """

  @download
  Scenario: Assert the downloaded file should be a zip archive containing the following files partially named
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "archive_multiple.zip"
    Then the downloaded file name should be "archive_multiple.zip"
    And the downloaded file should be a zip archive containing the following files partially named:
      | example_aud |
      | example_ima |

  @test-trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that negative assertion for "the downloaded file should be a zip archive containing the following files partially named" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I log in as a user with the role "administrator"
      When I visit the "article" content page with the title "[TEST] zip page"
      When I download the file from the link "archive_multiple.zip"
      And the downloaded file name should be "archive_multiple.zip"
      Then the downloaded file should be a zip archive containing the following files partially named:
        | nonexistent_file |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find any file partially named "nonexistent_file" in archive
      """

  @download
  Scenario: Assert the downloaded file is a zip archive not containing files partially named
    When I visit the "article" content page with the title "[TEST] zip page"
    When I download the file from the link "archive_multiple.zip"
    Then the downloaded file name should be "archive_multiple.zip"
    And the downloaded file should be a zip archive not containing the following files partially named:
      | example_text |
      | not_existing |

  @test-trait:FileDownloadTrait
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
      Link with text "nonexistent_link" not found.
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that file name mismatch fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download @phpserver":
      """
      When I visit "/"
      And I download the file from the URL "http://cli:8888/text.txt"
      Then the downloaded file name should be "wrong_name.txt"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file "text.txt", but expected "wrong_name.txt"
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that file content not found fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download @phpserver":
      """
      When I visit "/"
      And I download the file from the URL "http://cli:8888/text.txt"
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

  @test-trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that zip archive with missing files fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I log in as a user with the role "administrator"
      When I visit the "article" content page with the title "[TEST] zip page"
      When I download the file from the link "archive_multiple.zip"
      Then the downloaded file should be a zip archive containing the following files named:
        | nonexistent1.txt |
        | nonexistent2.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find file "nonexistent1.txt" in archive
      """

  @test-trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that zip archive with found excluded files fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download":
      """
      When I log in as a user with the role "administrator"
      When I visit the "article" content page with the title "[TEST] zip page"
      When I download the file from the link "archive_multiple.zip"
      Then the downloaded file should be a zip archive not containing the following files partially named:
        | example_audio |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Found file partially named "example_audio" in archive, but it should not
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that the skip tag switches the FileDownloadTrait hooks off
    Given some behat configuration
    And scenario steps tagged with "@download @behat-steps-skip:FileDownloadTrait":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:FileDownloadTrait
  Scenario: Assert that the @download tag on the feature applies to every scenario
    Given some behat configuration
    And a file named "features/stub.feature" with:
      """
      @download @phpserver
      Feature: Stub feature

        Scenario: File is downloaded
          When I visit "/"
          And I download the file from the URL "http://cli:8888/text.txt"
          Then the downloaded file should contain:
            '''
            Some Text
            '''
      """
    When I run "behat --no-colors"
    Then it should pass with:
      """
      1 scenario (1 passed)
      """

  @test-trait:FileDownloadTrait
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

  @test-trait:FileDownloadTrait
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

  @test-trait:FileDownloadTrait
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

  @test-trait:FileDownloadTrait,Drupal\ContentTrait
  Scenario: Assert that invalid ZIP file fails with an error
    Given some behat configuration
    And the following managed files exist:
      | path                |
      | archive_invalid.zip |
    And scenario steps tagged with "@download @phpserver":
      """
      When I log in as a user with the role "administrator"
      When I download the file from the URL "http://cli:8888/archive_invalid.zip"
      Then the downloaded file should be a zip archive containing the following files named:
        | test.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file is not a valid ZIP file.
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that ZIP assertion without download fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the downloaded file should be a zip archive containing the following files named:
        | test.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Downloaded file path data is not available.
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that ZIP assertion on non-ZIP file fails with an error
    Given some behat configuration
    And scenario steps tagged with "@download @phpserver":
      """
      When I visit "/"
      And I download the file from the URL "http://cli:8888/text.txt"
      Then the downloaded file should be a zip archive containing the following files named:
        | test.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Downloaded file does not have correct headers set for ZIP.
      """

  @test-trait:FileDownloadTrait
  Scenario: Assert that downloading a URL returning an error status fails
    Given some behat configuration
    And scenario steps tagged with "@download @phpserver":
      """
      When I visit "/"
      And I download the file from the URL "http://cli:8888/nonexistent-download-target.txt"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      returned HTTP status 404
      """
