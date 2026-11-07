Feature: Check that FileTrait works
  As Behat Steps library developer
  I want to provide tools to manage files programmatically
  So that users can test file functionality and operations

  Scenario: Assert "When the following managed files exist:"
    Given I am logged in as a user with the role "administrator"
    When the following managed files exist:
      | path         |
      | document.pdf |
      | image.png    |
      | audio.mp3    |
    And the following managed files exist:
      | uuid                                 | path     |
      | 9cb1b484-db7b-4496-bd63-8c702e207704 | text.txt |
    Then "document.pdf" file object exists
    And "image.png" file object exists
    And "audio.mp3" file object exists
    And "text.txt" file object exists
    And "file" entity exists with UUID "9cb1b484-db7b-4496-bd63-8c702e207704"

  Scenario: Assert "When the following managed files exist: With subdirectory path"
    Given I am logged in as a user with the role "administrator"
    When the following managed files exist:
      | path                  |
      | subdir/document.pdf   |
    Then "document.pdf" file object exists

  Scenario: Assert "When the following managed files exist: With uri"
    Given I am logged in as a user with the role "administrator"
    And no "document.pdf" file object exists
    And no "image.png" file object exists
    And no "audio.mp3" file object exists
    When the following managed files exist:
      | path         | uri                        |
      | document.pdf | public://test/document.pdf |
      | image.png    | public://test/image.png    |
      | audio.mp3    | public://test/audio.mp3    |
    Then "document.pdf" file object exists
    And "image.png" file object exists
    And "audio.mp3" file object exists

  Scenario: Assert "When the following managed files do not exist: With filename"
    Given I am logged in as a user with the role "administrator"
    When the following managed files exist:
      | path         |
      | document.pdf |
      | image.png    |
      | audio.mp3    |
    Then "document.pdf" file object exists
    And "image.png" file object exists
    And "audio.mp3" file object exists
    When the following managed files do not exist:
      | filename     |
      | document.pdf |
      | image.png    |
      | audio.mp3    |
    Then no "document.pdf" file object exists
    And no "image.png" file object exists
    And no "audio.mp3" file object exists

  Scenario: Assert "When the following managed files do not exist: With uri"
    Given I am logged in as a user with the role "administrator"
    When the following managed files exist:
      | path         |
      | document.pdf |
      | image.png    |
      | audio.mp3    |
    Then "document.pdf" file object exists
    And "image.png" file object exists
    And "audio.mp3" file object exists
    When the following managed files do not exist:
      | uri                   |
      | public://document.pdf |
      | public://image.png    |
      | public://audio.mp3    |
    Then no "document.pdf" file object exists
    And no "image.png" file object exists
    And no "audio.mp3" file object exists

  Scenario: Assert "When the following managed files do not exist: With status"
    Given I am logged in as a user with the role "administrator"
    When the following managed files exist:
      | path         |
      | document.pdf |
      | image.png    |
      | audio.mp3    |
    Then "document.pdf" file object exists
    And "image.png" file object exists
    And "audio.mp3" file object exists
    When the following managed files do not exist:
      | status |
      | 1      |
    Then no "document.pdf" file object exists
    And no "image.png" file object exists
    And no "audio.mp3" file object exists

  Scenario: Assert "When the following managed files do not exist: With filemime"
    Given I am logged in as a user with the role "administrator"
    When the following managed files exist:
      | path         |
      | document.pdf |
      | image.png    |
      | audio.mp3    |
    Then "document.pdf" file object exists
    And "image.png" file object exists
    And "audio.mp3" file object exists
    When the following managed files do not exist:
      | filemime  |
      | image/png |
    Then "document.pdf" file object exists
    And no "image.png" file object exists
    And "audio.mp3" file object exists

  Scenario: Assert unmanaged files step definitions
    Given the unmanaged file at the URI "public://test1.txt" should not exist
    When the unmanaged file at the URI "public://test1.txt" exists
    Then the unmanaged file at the URI "public://test1.txt" should exist
    And the unmanaged file at the URI "public://test2.txt" should not exist

    Given the unmanaged file at the URI "public://test3.txt" should not exist
    When the unmanaged file at the URI "public://test3.txt" exists with the content "test content"
    Then the unmanaged file at the URI "public://test3.txt" should exist
    And the unmanaged file at the URI "public://test3.txt" should contain "test content"
    And the unmanaged file at the URI "public://test3.txt" should contain "content"
    And the unmanaged file at the URI "public://test3.txt" should not contain "test more content"

    Given the unmanaged file at the URI "public://test-random/test4.txt" should not exist
    When the unmanaged file at the URI "public://test-random/test4.txt" exists with the content "test content"
    Then the unmanaged file at the URI "public://test-random/test4.txt" should exist

  @csv
  Scenario: Assert the rows, row count and columns of a CSV file at a stream wrapper URI
    Given the following managed files exist:
      | path           | uri                        |
      | csv_report.csv | temporary://csv_report.csv |
    Then the CSV file at the URI "temporary://csv_report.csv" should have the following columns:
      | clip_id | title | result |
    And the CSV file at the URI "temporary://csv_report.csv" should have 3 rows
    And the CSV file at the URI "temporary://csv_report.csv" should contain the following row:
      | result  | updated |
      | clip_id | clip-1  |
    And the CSV file at the URI "temporary://csv_report.csv" should contain the following row:
      | clip_id | clip-1                   |
      | title   | First clip, with a comma |
    And the CSV file at the URI "temporary://csv_report.csv" should contain the following row:
      | title | Second "quoted" clip |
    And the CSV file at the URI "temporary://csv_report.csv" should contain the following row:
      | clip_id | clip-3  |
      | result  | skipped |
    And the CSV file at the URI "temporary://csv_report.csv" should not contain the following row:
      | clip_id | clip-1  |
      | result  | created |
    And the CSV file at the URI "temporary://csv_report.csv" should not contain the following row:
      | clip_id | clip-4 |

  @test-trait:Drupal\FileTrait @csv
  Scenario: Assert that "Then the CSV file at the URI :uri should contain the following row:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the following managed files exist:
        | path           | uri                        |
        | csv_report.csv | temporary://csv_report.csv |
      Then the CSV file at the URI "temporary://csv_report.csv" should contain the following row:
        | clip_id | clip-1  |
        | result  | created |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "temporary://csv_report.csv" has no row where the column "clip_id" has the value "clip-1" and the column "result" has the value "created".
      """

  @test-trait:Drupal\FileTrait @csv
  Scenario: Assert that "Then the CSV file at the URI :uri should not contain the following row:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the following managed files exist:
        | path           | uri                        |
        | csv_report.csv | temporary://csv_report.csv |
      Then the CSV file at the URI "temporary://csv_report.csv" should not contain the following row:
        | clip_id | clip-2 |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "temporary://csv_report.csv" has a row where the column "clip_id" has the value "clip-2", but it should not.
      """

  @test-trait:Drupal\FileTrait @csv
  Scenario: Assert that a CSV row step naming a column the file does not have fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the following managed files exist:
        | path           | uri                        |
        | csv_report.csv | temporary://csv_report.csv |
      Then the CSV file at the URI "temporary://csv_report.csv" should not contain the following row:
        | status | failed |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "temporary://csv_report.csv" has no column "status".
      """

  @test-trait:Drupal\FileTrait @csv
  Scenario: Assert that "Then the CSV file at the URI :uri should have :count row(s)" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the following managed files exist:
        | path           | uri                        |
        | csv_report.csv | temporary://csv_report.csv |
      Then the CSV file at the URI "temporary://csv_report.csv" should have 4 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "temporary://csv_report.csv" has 3 data row(s), but it should have 4.
      """

  @test-trait:Drupal\FileTrait @csv
  Scenario: Assert that a CSV row count that is not an integer fails with an exception
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file at the URI "temporary://csv_report.csv" should have many rows
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The count must be an integer, but "many" was given.
      """

  @test-trait:Drupal\FileTrait @csv
  Scenario: Assert that "Then the CSV file at the URI :uri should have the following columns:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the following managed files exist:
        | path           | uri                        |
        | csv_report.csv | temporary://csv_report.csv |
      Then the CSV file at the URI "temporary://csv_report.csv" should have the following columns:
        | clip_id | result |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "temporary://csv_report.csv" has the columns "clip_id", "title", "result", but it should have the columns "clip_id", "result".
      """

  @test-trait:Drupal\FileTrait @csv
  Scenario: Assert that a CSV step on a file that does not exist fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file at the URI "temporary://csv_missing.csv" should have 0 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file "temporary://csv_missing.csv" does not exist.
      """

  @test-trait:Drupal\FileTrait
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the unmanaged file at the URI "public://test4.txt" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file "public://test4.txt" does not exist.
      """

  @test-trait:Drupal\FileTrait
  Scenario: Assert that negative assertion for "Then the unmanaged file at the URI :uri should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the unmanaged file at the URI "public://test1.txt" exists
      Then the unmanaged file at the URI "public://test1.txt" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file "public://test1.txt" exists, but it should not.
      """

  @test-trait:Drupal\FileTrait
  Scenario: Assert that negative assertion for "Then the unmanaged file at the URI :uri should contain :content" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the unmanaged file at the URI "public://test1.txt" exists with the content "test content"
      Then the unmanaged file at the URI "public://test1.txt" should exist
      And the unmanaged file at the URI "public://test1.txt" should contain "test other content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file content "test content" does not contain "test other content".
      """

  @test-trait:Drupal\FileTrait
  Scenario: Assert that negative assertion for "Then the unmanaged file at the URI :uri should not contain :content" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the unmanaged file at the URI "public://test1.txt" exists with the content "test content"
      Then the unmanaged file at the URI "public://test1.txt" should exist
      And the unmanaged file at the URI "public://test1.txt" should not contain "test content"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The file content "test content" contains "test content", but it should not.
      """

  @test-trait:Drupal\FileTrait
  Scenario: A managed files table whose first column is a number fails with an exception
    Given some behat configuration
    And scenario steps:
      """
      Given the following managed files do not exist:
        | 1 |
        | 2 |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The first column should be the field name.
      """

  @test-trait:Drupal\FileTrait
  Scenario: A managed file whose path does not exist fails with an exception
    Given some behat configuration
    And scenario steps:
      """
      Given the following managed files exist:
        | path        |
        | missing.txt |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find file "missing.txt".
      """

  @test-trait:Drupal\FileTrait
  Scenario: Assert that the skip tag switches the FileTrait hooks off
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:FileTrait":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\FileTrait
  Scenario: Assert that a configuration reaching Drupal only through Drush passes
    Given a configuration listing the backends "drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass
