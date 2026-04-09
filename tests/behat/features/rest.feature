Feature: Check that RestTrait works
  As Behat Steps library developer
  I want to provide tools for REST API testing
  So that users can send HTTP requests and assert responses

  Scenario: Assert "Given a REST header :name with value :value" and "When I send a REST :method request to :url" work
    Given a REST header "Accept" with value "text/html"
    When I send a REST "GET" request to "/"
    Then the REST response status code should be 200

  Scenario: Assert "When I send a REST :method request to :url with body:" works
    Given a REST header "Content-Type" with value "text/plain"
    When I send a REST "POST" request to "/" with body:
      """
      test body content
      """
    Then the REST response status code should be 200

  Scenario: Assert multiple headers can be set
    Given a REST header "Accept" with value "text/html"
    And a REST header "X-Custom-Header" with value "custom-value"
    When I send a REST "GET" request to "/"
    Then the REST response status code should be 200

  Scenario: Assert "Then the REST response should contain :text" works
    When I send a REST "GET" request to "/"
    Then the REST response should contain "html"

  @trait:RestTrait
  Scenario: Assert that negative assertion for "Then the REST response status code should be :code" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/"
      Then the REST response status code should be 404
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected response status code 404, but got 200.
      """

  @trait:RestTrait
  Scenario: Assert that negative assertion for "Then the REST response should contain :text" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/"
      Then the REST response should contain "nonexistingtext12345"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response does not contain "nonexistingtext12345".
      """
