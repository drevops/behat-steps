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

  Scenario: Assert "When I send a REST :method request to :url" sends none of the browser's cookies
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

  Scenario: Assert "When I send a REST :method request to :url with the body:" sends none of the browser's cookies
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

  Scenario: Assert a REST request is anonymous without the browser's login
    Given I am logged in as a user with the role "administrator"
    When I send a REST "GET" request to "/admin"
    Then the REST response status code should be 403

  Scenario: Assert a REST request leaves the browser's page in place
    Given I go to "/user/login"
    When I send a REST "GET" request to "/mysite_core/test-robots-header"
    Then the REST response should contain "Robots header test"
    And the path should be "/user/login"
    And I should not see the heading "Robots header test"

  Scenario: Assert a REST request carries the basic authentication a step set
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    And I am using basic authentication with the username "admin-test" and the password "admin-test"
    When I send a REST "GET" request to "/mysite_core/test-basic-auth"
    Then the REST response status code should be 200
    And the REST response should contain "admin-test"

  Scenario: Assert a REST "Authorization" header replaces the basic authentication a step set
    Given the following users exist:
      | name        | mail                | pass        |
      | admin-test  | admin-test@bar.com  | admin-test  |
      | editor-test | editor-test@bar.com | editor-test |
    And I am using basic authentication with the username "admin-test" and the password "admin-test"
    And the REST header "Authorization" has the value "Basic ZWRpdG9yLXRlc3Q6ZWRpdG9yLXRlc3Q="
    When I send a REST "GET" request to "/mysite_core/test-basic-auth"
    Then the REST response status code should be 200
    And the REST response should contain "editor-test"

  @javascript
  Scenario: Assert a REST request runs beside a JavaScript browser session
    Given I go to "/user/login"
    When I send a REST "GET" request to "/mysite_core/test-robots-header"
    Then the REST response status code should be 200
    And the REST response should contain "Robots header test"
    And the path should be "/user/login"

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
  Scenario: Assert that "Then the REST response status code should be :code" fails before any REST request
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/"
      Then the REST response status code should be 200
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No REST request has been sent in this scenario. The REST, JSON and XML steps read the response of "When I send a REST :method request to :url", not the page the browser loaded.
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
