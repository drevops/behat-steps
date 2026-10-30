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

  @rest-body-file
  Scenario: Assert "When I send a REST :method request to :url with the body from the file :filename" sends the file content
    Given the REST header "Content-Type" has the value "application/xml"
    When I send a REST "POST" request to "/mysite_core/test-request-echo" with the body from the file "rest_body.xml"
    Then the REST response status code should be 200
    And the REST response should contain "content-type: application/xml"
    And the REST response should contain "<items>"
    And the REST response should contain ">Second item</item>"

  @phpserver @rest-json-header
  Scenario: Assert "Given the REST header :name has the value from the JSON path :path" sends the value
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    When I send a REST "GET" request to "http://cli:8888/json_token.json"
    Then the REST response status code should be 200
    Given the REST header "Authorization" has the value from the JSON path "$.authorization"
    When I send a REST "GET" request to "/mysite_core/test-basic-auth"
    Then the REST response status code should be 200
    And the REST response should contain "admin-test"

  @phpserver @rest-json-header
  Scenario: Assert "Given the REST header :name has the value from the JSON path :path with the prefix :prefix" sends the prefix and the value
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    When I send a REST "GET" request to "http://cli:8888/json_token.json"
    Then the JSON path "$.access_token" should exist
    Given the REST header "Authorization" has the value from the JSON path "$.access_token" with the prefix "Basic "
    When I send a REST "GET" request to "/mysite_core/test-basic-auth"
    Then the REST response status code should be 200
    And the REST response should contain "admin-test"

  @phpserver @rest-json-header
  Scenario: Assert "Given the REST header :name has the value from the JSON path :path" sends a number and a boolean as text
    When I send a REST "GET" request to "http://cli:8888/json_token.json"
    Given the REST header "X-Expires-In" has the value from the JSON path "$.expires_in"
    And the REST header "X-Refresh" has the value from the JSON path "$.refresh" with the prefix "refresh="
    When I send a REST "POST" request to "/mysite_core/test-request-echo"
    Then the REST response status code should be 200
    And the REST response should contain "x-expires-in: 3600"
    And the REST response should contain "x-refresh: refresh=true"

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

  Scenario: Assert the REST response header steps work
    When I send a REST "GET" request to "/mysite_core/test-robots-header?value=noindex"
    Then the REST response should contain the header "X-Robots-Tag"
    And the REST response should not contain the header "X-Nonexistent-Header"
    And the REST response header "X-Robots-Tag" should contain "NOINDEX"
    And the REST response header "X-Robots-Tag" should not contain "nofollow"

  Scenario: Assert the REST response header steps read the REST response, not the page
    Given I go to "/mysite_core/test-robots-header?value=nofollow"
    When I send a REST "GET" request to "/mysite_core/test-robots-header?value=noindex"
    Then the REST response header "X-Robots-Tag" should contain "noindex"
    And the response header "X-Robots-Tag" should contain "nofollow"

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
  Scenario: Assert that "Then the REST response should contain the header :name" fails with an error for a missing header
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header"
      Then the REST response should contain the header "X-Nonexistent-Header"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response does not contain the header "X-Nonexistent-Header".
      """

  @test-trait:RestTrait
  Scenario: Assert that "Then the REST response should not contain the header :name" fails with an error for a present header
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header"
      Then the REST response should not contain the header "X-Robots-Tag"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response contains the header "X-Robots-Tag", but it should not.
      """

  @test-trait:RestTrait
  Scenario: Assert that "Then the REST response header :name should contain :value" fails with an error for a missing header
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header"
      Then the REST response header "X-Nonexistent-Header" should contain "noindex"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response does not contain the header "X-Nonexistent-Header".
      """

  @test-trait:RestTrait
  Scenario: Assert that "Then the REST response header :name should contain :value" fails with an error for another value
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header?value=noindex"
      Then the REST response header "X-Robots-Tag" should contain "nofollow"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response header "X-Robots-Tag" is "noindex" and does not contain "nofollow".
      """

  @test-trait:RestTrait
  Scenario: Assert that "Then the REST response header :name should not contain :value" fails with an error for a missing header
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header"
      Then the REST response header "X-Nonexistent-Header" should not contain "noindex"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response does not contain the header "X-Nonexistent-Header".
      """

  @test-trait:RestTrait
  Scenario: Assert that "Then the REST response header :name should not contain :value" fails with an error for a contained value
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header?value=noindex"
      Then the REST response header "X-Robots-Tag" should not contain "noindex"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The REST response header "X-Robots-Tag" is "noindex" and contains "noindex", but it should not.
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

  @test-trait:RestTrait
  Scenario: Assert that "Given the REST header :name has the value from the JSON path :path" fails before any REST request
    Given some behat configuration
    And scenario steps:
      """
      Given the REST header "Authorization" has the value from the JSON path "$.access_token"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No REST request has been sent in this scenario. The REST, JSON and XML steps read the response of "When I send a REST :method request to :url", not the page the browser loaded.
      """

  @test-trait:RestTrait
  Scenario: Assert that "Given the REST header :name has the value from the JSON path :path" fails with an exception for a response that is not JSON
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "GET" request to "/mysite_core/test-robots-header"
      Given the REST header "Authorization" has the value from the JSON path "$.access_token"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Failed to decode JSON: Syntax error.
      """

  @test-trait:RestTrait
  Scenario: Assert that "Given the REST header :name has the value from the JSON path :path" fails with an error for a missing path
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I send a REST "GET" request to "http://cli:8888/json_token.json"
      Given the REST header "Authorization" has the value from the JSON path "$.missing"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.missing" was not found.
      """

  @test-trait:RestTrait
  Scenario: Assert that "Given the REST header :name has the value from the JSON path :path" fails with an error for a null value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I send a REST "GET" request to "http://cli:8888/json_token.json"
      Given the REST header "X-Scope" has the value from the JSON path "$.scope"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.scope" is null in the REST response, but a value is required.
      """

  @test-trait:RestTrait
  Scenario: Assert that "Given the REST header :name has the value from the JSON path :path with the prefix :prefix" fails with an error for an array
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I send a REST "GET" request to "http://cli:8888/json_token.json"
      Given the REST header "X-Scopes" has the value from the JSON path "$.scopes" with the prefix "scopes="
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.scopes" resolves to an array or object, but a scalar value is required for this assertion.
      """

  @test-trait:RestTrait
  Scenario: Assert that "Given the REST header :name has the value from the JSON path :path" fails with an error for several matches
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I send a REST "GET" request to "http://cli:8888/json_token.json"
      Given the REST header "X-Scope" has the value from the JSON path "$.scopes[*]"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The JSON path "$.scopes[*]" matched 2 values, but a single value is required for this assertion.
      """

  @test-trait:RestTrait
  Scenario: Assert that "When I send a REST :method request to :url with the body from the file :filename" fails with an exception for a missing file
    Given some behat configuration
    And scenario steps:
      """
      When I send a REST "POST" request to "/mysite_core/test-request-echo" with the body from the file "rest_missing.xml"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      rest_missing.xml" does not exist.
      """
