Feature: Check that RestTrait works
  As Behat Steps library developer
  I want to provide tools for REST API testing
  So that users can send HTTP requests and assert responses

  Scenario: Assert "Given the REST header :name has the value :value" and "When I send a REST :method request to :url" work
    Given the REST header "Accept" has the value "text/html"
    When I send a REST "GET" request to "/"
    Then the REST response status code should be 200

  Scenario: Assert "When I send a REST :method request to :url with the body:" works
    Given the REST header "Content-Type" has the value "text/plain"
    When I send a REST "POST" request to "/" with the body:
      """
      test body content
      """
    Then the REST response status code should be 200

  Scenario: Assert multiple headers can be set
    Given the REST header "Accept" has the value "text/html"
    And the REST header "X-Custom-Header" has the value "custom-value"
    When I send a REST "GET" request to "/"
    Then the REST response status code should be 200

  Scenario: Assert "Then the REST response should contain :value" works
    When I send a REST "GET" request to "/"
    Then the REST response should contain "html"

  @phpserver
  Scenario: Assert "When I send a REST :method request to :url" sends an absolute URL as it is
    Given the REST header "Accept" has the value "application/json"
    When I send a REST "GET" request to "http://cli:8888/json_valid.json"
    Then the REST response status code should be 200
    And the REST response should contain "The Great Adventure"

  Scenario: Assert "When I send a REST :method request to :url" sends the session cookies by default
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    And I am logged in as a user with the role "administrator"
    And the REST header "Authorization" has the value "Basic YWRtaW4tdGVzdDphZG1pbi10ZXN0"
    When I send a REST "POST" request to "/mysite_core/test-csrf-header"
    Then the REST response status code should be 403

  @rest-no-cookies
  Scenario: Assert "When I send a REST :method request to :url" sends no session cookies with the "@rest-no-cookies" tag
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    And I am logged in as a user with the role "administrator"
    And the REST header "Authorization" has the value "Basic YWRtaW4tdGVzdDphZG1pbi10ZXN0"
    When I send a REST "POST" request to "/mysite_core/test-csrf-header"
    Then the REST response status code should be 200
    And the REST response should contain "admin-test"
    When I go to "/admin"
    Then the response status code should be 200

  @rest-no-cookies
  Scenario: Assert "When I send a REST :method request to :url with the body:" sends no session cookies with the "@rest-no-cookies" tag
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    And I am logged in as a user with the role "administrator"
    And the REST header "Authorization" has the value "Basic YWRtaW4tdGVzdDphZG1pbi10ZXN0"
    And the REST header "Content-Type" has the value "application/json"
    When I send a REST "POST" request to "/mysite_core/test-csrf-header" with the body:
      """
      {"name": "example"}
      """
    Then the REST response status code should be 200
    And the REST response should contain "admin-test"

  @behat-steps-skip:RestTrait
  Scenario: Assert that a scenario with the skip tag still sends REST requests
    Given the REST header "Accept" has the value "text/html"
    When I send a REST "GET" request to "/"
    Then the REST response status code should be 200

  @test-trait:RestTrait
  Scenario: Assert "When I print the last REST response" prints the response body
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header"
      And I print the last REST response
      """
    When I run "behat --no-colors"
    Then it should pass with:
      """
      1 scenario (1 passed)
      """
    And the output should contain:
      """
      <h1>Robots header test</h1>
      """

  @test-trait:RestTrait
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
  Scenario: Assert that negative assertion for "Then the REST response should contain :value" fails with an error
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
