Feature: Check that CsvTrait works
  As Behat Steps library developer
  I want to provide tools to assert the contents of CSV files
  So that users can test the exports, reports and logs a site writes

  @test-trait:CsvTrait
  Scenario: Assert the columns, rows and values of a CSV file
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should have the following columns:
        | clip_id | title | result |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should have the column "result"
      And the CSV file "tests/behat/fixtures/csv_report.csv" should not have the column "status"
      And the CSV file "tests/behat/fixtures/csv_report.csv" should have 3 rows
      And the CSV file "tests/behat/fixtures/csv_report.csv" should contain the following row:
        | result  | updated |
        | clip_id | clip-1  |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should contain the following row:
        | clip_id | clip-1                   |
        | title   | First clip, with a comma |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should contain the following row:
        | title | Second "quoted" clip |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should not contain the following row:
        | clip_id | clip-1  |
        | result  | created |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should contain the following rows:
        | clip_id | result  |
        | clip-3  | skipped |
        | clip-1  | updated |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should not contain the following rows:
        | clip_id | result |
        | clip-1  | failed |
        | clip-4  | failed |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should have the following rows:
        | clip_id | result  |
        | clip-1  | updated |
        | clip-2  | created |
        | clip-3  | skipped |
      And the CSV file "tests/behat/fixtures/csv_report.csv" should contain "on 2 lines"
      And the CSV file "tests/behat/fixtures/csv_report.csv" should not contain "failed"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:CsvTrait
  Scenario: Assert that a CSV file with only a header row has no rows
    Given some behat configuration
    And a file named "report.csv" with:
      """
      clip_id,result
      """
    And scenario steps:
      """
      Then the CSV file "report.csv" should have 0 rows
      And the CSV file "report.csv" should not contain the following row:
        | clip_id | clip-1 |
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should have the following columns:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should have the following columns:
        | clip_id | result |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has the columns "clip_id", "title", "result", but it should have the columns "clip_id", "result".
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should have the column :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should have the column "status"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has no column "status".
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should not have the column :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should not have the column "result"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has the column "result", but it should not.
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should have :count row(s)" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should have 4 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has 3 data row(s), but it should have 4.
      """

  @test-trait:CsvTrait
  Scenario: Assert that a CSV row count that is not an integer fails with an exception
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should have many rows
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The count must be an integer, but "many" was given.
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should contain the following row:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should contain the following row:
        | clip_id | clip-1  |
        | result  | created |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has no row where the column "clip_id" has the value "clip-1" and the column "result" has the value "created".
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should not contain the following row:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should not contain the following row:
        | clip_id | clip-2 |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has a row where the column "clip_id" has the value "clip-2", but it should not.
      """

  @test-trait:CsvTrait
  Scenario: Assert that a CSV row step naming a column the file does not have fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should not contain the following row:
        | status | failed |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has no column "status".
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should contain the following rows:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should contain the following rows:
        | clip_id | result  |
        | clip-1  | updated |
        | clip-2  | failed  |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has no row where the column "clip_id" has the value "clip-2" and the column "result" has the value "failed".
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should not contain the following rows:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should not contain the following rows:
        | clip_id | result  |
        | clip-1  | failed  |
        | clip-3  | skipped |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has a row where the column "clip_id" has the value "clip-3" and the column "result" has the value "skipped", but it should not.
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should have the following rows:" fails with an error for a different value
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should have the following rows:
        | clip_id | result  |
        | clip-1  | updated |
        | clip-2  | failed  |
        | clip-3  | skipped |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The data row 2 of the CSV file "tests/behat/fixtures/csv_report.csv" has the value "created" in the column "result", but it should have the value "failed".
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should have the following rows:" fails with an error for a different row count
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should have the following rows:
        | clip_id |
        | clip-1  |
        | clip-2  |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" has 3 data row(s), but it should have 2.
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should contain :value" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should contain "Fourth clip"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" does not contain "Fourth clip".
      """

  @test-trait:CsvTrait
  Scenario: Assert that "Then the CSV file :path should not contain :value" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "tests/behat/fixtures/csv_report.csv" should not contain "skipped"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "tests/behat/fixtures/csv_report.csv" contains "skipped", but it should not.
      """

  @test-trait:CsvTrait
  Scenario: Assert that a CSV step on a file that does not exist fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Then the CSV file "missing.csv" should have 0 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSV file "missing.csv" does not exist.
      """

  @test-trait:CsvTrait
  Scenario: Assert that a CSV step on a file with a short row fails with an error
    Given some behat configuration
    And a file named "report.csv" with:
      """
      clip_id,result
      clip-1,updated
      clip-2
      """
    And scenario steps:
      """
      Then the CSV file "report.csv" should have 2 rows
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The data row 2 of the CSV file "report.csv" has 1 value(s), but the header has 2 column(s).
      """
