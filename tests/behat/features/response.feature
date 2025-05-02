Feature: Check that ResponseTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTTP response headers
  So that users can test server configuration and content delivery

  Scenario: Assert "Then the response should contain the header :header_name" works
    When I go to "/"
    Then the response should contain the header "Content-Type"

  @trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response should contain the header :header_name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I go to "/"
      Then the response should contain the header "NonExistingHeader"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response does not contain the header "NonExistingHeader".
      """

  Scenario: Assert "Then the response should not contain the header :header_name" works
    When I go to "/"
    Then the response should not contain the header "NonExistingHeader"

  @trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response should not contain the header :header_name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response should not contain the header "Content-Type"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The response contains the header "Content-Type", but should not.
      """

  Scenario: Assert "Then the response header :header_name should contain the value :header_value" works
    When I go to "/"
    Then the response header "Content-Type" should contain the value "text/html; charset=utf-8"

  @trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :header_name should contain the value :header_value" fails with an exception for missing header
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "NonExistingHeader" should contain the value "text/html; charset=utf-8"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The response does not contain the header "NonExistingHeader".
      """

  @trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :header_name should contain the value :header_value" fails with an error for invalid header value
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "Content-Type" should contain the value "nonexistingvalue"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ExpectationException" exception:
      """
      The text "nonexistingvalue" was not found anywhere in the "Content-Type" response header.
      """

  Scenario: Assert "Then the response header :header_name should not contain the value :header_value" works
    When I go to "/"
    Then the response header "Content-Type" should not contain the value "nonexistingvalue"

  @trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :header_name should not contain the value :header_value" fails with an exception for missing header
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "NonExistingHeader" should not contain the value "nonexistingvalue"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The response does not contain the header "NonExistingHeader".
      """

  @trait:ResponseTrait
  Scenario: Assert that negative assertion for "Then the response header :header_name should not contain the value :header_value" fails with an error for invalid header value
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the response header "Content-Type" should not contain the value "text/html; charset=utf-8"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ExpectationException" exception:
      """
      The text "text/html; charset=utf-8" was found in the "Content-Type" response header, but it should not.
      """
