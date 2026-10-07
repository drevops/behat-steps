Feature: Check that JsonTrait works
  As Behat Steps library developer
  I want to provide tools to assert JSON responses
  So that users can test API endpoints returning JSON

  @phpserver
  Scenario: Assert "Then the response should be in JSON format" works
    When I go to "http://cli:8888/json_valid.json"
    Then the response should be in JSON format

  @phpserver
  Scenario: Assert "Then the response should be in JSON format" honors content set from a fixture file over the page content
    When I go to "http://cli:8888/json_invalid.json"
    And the response JSON is loaded from the file "json_valid.json"
    Then the response should be in JSON format

  @phpserver
  Scenario: Assert "Then the response should be in JSON format" honors content set from a PyString over the page content
    When I go to "http://cli:8888/json_invalid.json"
    And the response JSON is the following:
      """
      {"name": "Blue Widget", "price": 9.99}
      """
    Then the response should be in JSON format

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the response should be in JSON format" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_invalid.json"
      Then the response should be in JSON format
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response is not valid JSON
      """

  @phpserver
  Scenario: Assert "Then the response should not be in JSON format" works
    When I go to "http://cli:8888/json_invalid.json"
    Then the response should not be in JSON format

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the response should not be in JSON format" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the response should not be in JSON format
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response is valid JSON, but it should not be.
      """

  Scenario: Assert "Given the response JSON is loaded from the file :filename" works
    Given the response JSON is loaded from the file "json_valid.json"
    Then the JSON path "$.name" should be equal to the value "John Doe"

  @test-trait:JsonTrait
  Scenario: Assert that "Given the response JSON is loaded from the file :filename" fails with an exception for missing file
    Given some behat configuration
    And scenario steps:
      """
      Given the response JSON is loaded from the file "nonexistent.json"
      Then the JSON path "$.name" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      does not exist
      """

  Scenario: Assert "Given the response JSON is the following:" works with direct PyString content
    Given the response JSON is the following:
      """
      {"name": "Blue Widget", "meta": {"sku": "p1"}, "tags": ["a", "b"]}
      """
    Then the JSON path "$.name" should be equal to the value "Blue Widget"
    And the JSON path "$.meta.sku" should be equal to the value "p1"
    And the JSON path "$.tags" should have "2" elements

  @test-trait:JsonTrait
  Scenario: Assert that path assertion fails with an exception for invalid JSON
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_invalid.json"
      Then the JSON path "$.name" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Failed to decode JSON
      """

  @test-trait:JsonTrait
  Scenario: Assert that path assertion fails with an exception for a non-object JSON root
    Given some behat configuration
    And scenario steps:
      """
      Given the response JSON is the following:
        '''
        42
        '''
      Then the JSON path "$.name" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The JSON response must decode to an array or object, but got int.
      """

  @test-trait:JsonTrait
  Scenario: Assert that path assertion fails with an error for an invalid JSONPath expression
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.items[?(@.x" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      is invalid
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should exist" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.name" should exist
    And the JSON path "$.user.roles[0]" should exist

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should exist" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.nonexistent" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.nonexistent" was not found.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should not exist" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.nonexistent" should not exist

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should not exist" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.name" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.name" was found, but it should not exist.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should be equal to the value :value" works with different scalar types
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.name" should be equal to the value "John Doe"
    And the JSON path "$.age" should be equal to the value "42"
    And the JSON path "$.price" should be equal to the value "9.99"
    And the JSON path "$.active" should be equal to the value "true"

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should be equal to the value :value" fails with an error for wrong value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.name" should be equal to the value "Wrong Name"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.name" is "John Doe", but expected "Wrong Name".
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the JSON path :path should be equal to the value :value" fails with an error for a missing path
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.nonexistent" should be equal to the value "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.nonexistent" was not found.
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the JSON path :path should be equal to the value :value" fails with an error for multiple matches
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.books[*].id" should be equal to the value "123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.books[*].id" matched 2 values, but a single value is required for this assertion.
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the JSON path :path should be equal to the value :value" fails with an error for a non-scalar value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.user" should be equal to the value "test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.user" resolves to an array or object, but a scalar value is required for this assertion.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should not be equal to the value :value" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.name" should not be equal to the value "Jane Roe"
    And the JSON path "$.nickname" should not be equal to the value "John Doe"

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should not be equal to the value :value" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.name" should not be equal to the value "John Doe"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.name" is "John Doe", but it should not be.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should contain the value :value" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.name" should contain the value "John"

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should contain the value :value" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.name" should contain the value "Nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.name" is "John Doe" and does not contain "Nonexistent".
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should not contain the value :value" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.name" should not contain the value "Nonexistent"

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should not contain the value :value" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.name" should not contain the value "John"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.name" is "John Doe" and contains "John", but it should not.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should match :pattern" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.email" should match "/^[^@]+@example\.com$/"

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should match :pattern" fails with an error for no match
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.email" should match "/^admin@/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      does not match the pattern
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the JSON path :path should match :pattern" fails with an error for an invalid pattern
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.email" should match "not-a-valid-regex"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The regular expression "not-a-valid-regex" is invalid.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should not match :pattern" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.email" should not match "/^admin@/"

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should not match :pattern" fails with an error when it matches
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.email" should not match "/@example\.com$/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      matches the pattern
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the JSON path :path should not match :pattern" fails with an error for an invalid pattern
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.email" should not match "not-a-valid-regex"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The regular expression "not-a-valid-regex" is invalid.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should be null" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.nickname" should be null

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should be null" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.name" should be null
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.name" is "John Doe", but expected null.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should be true" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.active" should be true

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should be true" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.disabled" should be true
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.disabled" is not true.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should be false" works
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.disabled" should be false

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should be false" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.active" should be false
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.active" is not false.
      """

  @phpserver
  Scenario: Assert "Then the JSON path :path should have :count element(s)" works for arrays and objects
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.items" should have "3" elements
    And the JSON path "$.user.roles" should have "2" elements
    And the JSON path "$.user" should have "1" element

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the JSON path :path should have :count element(s)" fails with an error for wrong count
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.items" should have "5" elements
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.items" has 3 element(s), but expected 5.
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the JSON path :path should have :count element(s)" fails with an error for a scalar value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.name" should have "1" element
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.name" is not an array or object.
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the JSON path :path should have :count element(s)" fails when the count is not an integer
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the JSON path "$.items" should have "three" elements
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The count must be an integer, but "three" was given.
      """

  @phpserver
  Scenario: Assert "Then the response should match the following JSON schema:" works
    When I go to "http://cli:8888/json_valid.json"
    Then the response should match the following JSON schema:
      """
      {"type": "object", "required": ["name", "age"], "properties": {"name": {"type": "string"}, "age": {"type": "integer"}}}
      """

  @test-trait:JsonTrait
  Scenario: Assert that negative assertion for "Then the response should match the following JSON schema:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the response JSON is the following:
        '''
        {"age": 42}
        '''
      Then the response should match the following JSON schema:
        '''
        {"type": "object", "required": ["name"], "properties": {"name": {"type": "string"}}}
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not match the JSON schema
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the response should match the following JSON schema:" fails with an error for an invalid schema
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the response should match the following JSON schema:
        '''
        {invalid schema
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The provided JSON schema is not valid JSON
      """

  @test-trait:JsonTrait
  Scenario: Assert that "Then the response should match the following JSON schema:" fails with an error for an invalid response body
    Given some behat configuration
    And scenario steps:
      """
      Given the response JSON is the following:
        '''
        {broken json
        '''
      Then the response should match the following JSON schema:
        '''
        {"type": "object"}
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response is not valid JSON
      """

  @phpserver
  Scenario: Assert "Then the response should match the JSON schema in the file :filename" works
    When I go to "http://cli:8888/json_valid.json"
    Then the response should match the JSON schema in the file "json_schema.json"

  @test-trait:JsonTrait
  Scenario: Assert that "Then the response should match the JSON schema in the file :filename" fails with an exception for missing file
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/json_valid.json"
      Then the response should match the JSON schema in the file "nonexistent.json"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      does not exist
      """

  Scenario: Assert "When I print the last JSON response" works
    Given the response JSON is loaded from the file "json_valid.json"
    When I print the last JSON response

  @test-trait:JsonTrait
  Scenario: Assert that "When I print the last JSON response" fails with an error for invalid JSON
    Given some behat configuration
    And scenario steps:
      """
      Given the response JSON is the following:
        '''
        {broken json
        '''
      When I print the last JSON response
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response is not valid JSON
      """

  @phpserver
  Scenario: Assert that JSON data is reloaded when navigating between different JSON files
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.name" should be equal to the value "John Doe"
    When I go to "http://cli:8888/json_alt.json"
    Then the JSON path "$.name" should be equal to the value "Jane Roe"
    And the JSON path "$.count" should be equal to the value "5"
    When I go to "http://cli:8888/json_valid.json"
    Then the JSON path "$.name" should be equal to the value "John Doe"
