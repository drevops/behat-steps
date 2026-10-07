Feature: Check that RestTrait works
  As Behat Steps library developer
  I want to provide tools for REST API testing
  So that users can send HTTP requests and assert responses

  Scenario: Assert "Given the REST header :name has the value :value" and "When I send a REST :method request to the URL :url" work
    Given the REST header "Accept" has the value "text/html"
    When I send a REST "GET" request to the URL "/"
    Then the REST response status code should be 200

  Scenario: Assert "When I send a REST :method request to the URL :url with the body:" works
    Given the REST header "Content-Type" has the value "text/plain"
    When I send a REST "POST" request to the URL "/" with the body:
      """
      test body content
      """
    Then the REST response status code should be 200

  Scenario: Assert multiple headers can be set
    Given the REST header "Accept" has the value "text/html"
    And the REST header "X-Custom-Header" has the value "custom-value"
    When I send a REST "GET" request to the URL "/"
    Then the REST response status code should be 200

  Scenario: Assert "Then the REST response should contain :text" works
    When I send a REST "GET" request to the URL "/"
    Then the REST response should contain "html"

  @test-trait:RestTrait
  Scenario: Assert that negative assertion for "Then the REST response status code should be :code" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to the URL "/"
      Then the REST response status code should be 404
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the REST response status code to be 404, but got 200.
      """

  @test-trait:RestTrait
  Scenario: Assert that "Then the REST response status code should be :code" fails when the code is not an integer
    Given some behat configuration
    And scenario steps:
      """
      Then the REST response status code should be "OK"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The status code must be an integer, but "OK" was given.
      """

  @test-trait:RestTrait
  Scenario: Assert that negative assertion for "Then the REST response should contain :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to the URL "/"
      Then the REST response should contain "nonexistingtext12345"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response does not contain "nonexistingtext12345".
      """
