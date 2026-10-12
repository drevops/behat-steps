Feature: Check that ResponseTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTTP response headers
  So that users can test server configuration and content delivery

  @phpserver
  Scenario: Assert "Then the response should contain the header :name" works
    When I go to "http://cli:8888/elements.html"
    Then the response should contain the header "Content-Type"

  @test-trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response should contain the header :name" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I go to "http://cli:8888/elements.html"
      Then the response should contain the header "NonExistingHeader"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not contain the header "NonExistingHeader".
      """

  @phpserver
  Scenario: Assert "Then the response should not contain the header :name" works
    When I go to "http://cli:8888/elements.html"
    Then the response should not contain the header "NonExistingHeader"

  @test-trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response should not contain the header :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response should not contain the header "Content-Type"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response contains the header "Content-Type", but it should not.
      """

  @phpserver
  Scenario: Assert "Then the response header :name should contain :value" works
    When I go to "http://cli:8888/elements.html"
    Then the response header "Content-Type" should contain "text/html"

  @test-trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :name should contain :value" fails with an error for missing header
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "NonExistingHeader" should contain "text/html; charset=utf-8"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not contain the header "NonExistingHeader".
      """

  @test-trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :name should contain :value" fails with an error for invalid header value
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "Content-Type" should contain "nonexistingvalue"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ExpectationException" exception:
      """
      The text "nonexistingvalue" was not found anywhere in the "Content-Type" response header.
      """

  @phpserver
  Scenario: Assert "Then the response header :name should not contain :value" works
    When I go to "http://cli:8888/elements.html"
    Then the response header "Content-Type" should not contain "nonexistingvalue"

  @test-trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :name should not contain :value" fails with an error for missing header
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "NonExistingHeader" should not contain "nonexistingvalue"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not contain the header "NonExistingHeader".
      """

  @test-trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :name should not contain :value" fails with an error for invalid header value
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "Content-Type" should not contain "text/html; charset=utf-8"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ExpectationException" exception:
      """
      The text "text/html; charset=utf-8" was found in the "Content-Type" response header, but it should not.
      """
