Feature: Check that CookieTrait works
  As Behat Steps library developer
  I want to provide tools to verify browser cookies and their values
  So that users can test session management and user preferences

  Scenario: Assert step definition "a cookie with( the) name :name should exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    And I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with the name "testname" should exist
    And a cookie with the name "testname" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name should exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    And I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with the name "testname" should exist
    And a cookie with the name "testname" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then a cookie with the name "testname" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was not set.
      """

  Scenario: Assert step definition "a cookie with( the) name :name and value :value should exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with the name "testname" and the value "testvalue" should exist
    And a cookie with the name "testname" and the value "testvalue" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name and value :value should exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with the name "testname" and the value "testvalue" should exist
    And a cookie with the name "testname" and the value "testvalue" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value :value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then a cookie with the name "testname" and the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was not set.
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value :value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then a cookie with the name "testothername" and the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testothername" was set with value "testothervalue", but it should be "testvalue".
      """

  Scenario: Assert step definition "a cookie with the name :name and a value containing :partial_value should exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with the name "testname" and a value containing "estva" should exist
    And a cookie with the name "testname" and a value containing "estva" should exist

  @javascript
  Scenario: Assert step definition "a cookie with the name :name and a value containing :partial_value should exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with the name "testname" and a value containing "estva" should exist
    And a cookie with the name "testname" and a value containing "estva" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with the name :name and a value containing :partial_value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with the name "testname" and a value containing "estva" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was not set.
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with the name :name and a value containing :partial_value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testname" and value "testothervalue"
      Then a cookie with the name "testname" and a value containing "estva" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set with value "testothervalue", but it should contain "estva".
      """

  Scenario: Assert step definition "a cookie with a name containing :partial_name should exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" should exist

  @javascript
  Scenario: Assert step definition "a cookie with a name containing :partial_name should exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with a name containing "estna" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was not set.
      """

  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and the value "testvalue" should exist

  @javascript
  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and the value "testvalue" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and the value :value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with a name containing "estna" and the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was not set.
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and the value :value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testname" and value "prefixtestvaluesuffix"
      Then a cookie with a name containing "estna" and the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was set with value "prefixtestvaluesuffix", but it should be "testvalue".
      """

  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and a value containing "estval" should exist

  @javascript
  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and a value containing "estval" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and a value containing :partial_value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with a name containing "estna" and a value containing "estval" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was not set.
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and a value containing :partial_value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testname" and value "testothervalue"
      Then a cookie with a name containing "estna" and a value containing "estval" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was set with value "testothervalue", but it should contain "estval".
      """

  #
  # NOT EXISTS
  #

  Scenario: Assert step definition "a cookie with the name :name should not exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with the name "testname" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with the name :name should not exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with the name "testname" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with the name :name should not exist" fails with an error when the cookie exists
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testname" and value "testvalue"
      Then a cookie with the name "testname" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set but it should not be.
      """

  Scenario: Assert step definition "a cookie with the name :name and the value :value should not exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with the name "testname" and the value "testvalue" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with the name :name and the value :value should not exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with the name "testname" and the value "testvalue" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with the name :name and the value :value should not exist" fails with an error when the cookie exists with the specified value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testname" and value "testvalue"
      Then a cookie with the name "testname" and the value "testvalue" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set with value "testvalue", but it should not be "testvalue".
      """

  Scenario: Assert step definition "a cookie with the name :name and a value containing :partial_value should not exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with the name "testname" and a value containing "testval" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with the name :name and a value containing :partial_value should not exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with the name "testname" and a value containing "testval" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with the name :name and a value containing :partial_value should not exist" fails with an error when the cookie exists with a value containing the partial value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "testname" and value "testvalue"
      Then a cookie with the name "testname" and a value containing "testval" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set with value containing "testvalue", but it should not contain "testval".
      """

  Scenario: Assert step definition "a cookie with a name containing :partial_name should not exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "othername" and value "testvalue"
    Then a cookie with a name containing "testname" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with a name containing :partial_name should not exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "othername" and value "testvalue"
    Then a cookie with a name containing "testname" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name should not exist" fails with an error when the cookie exists with a name containing the partial name
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "mytestname" and value "testvalue"
      Then a cookie with a name containing "testname" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "testname" was set but it should not be.
      """

  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should not exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "mytestname" and value "othervalue"
    Then a cookie with a name containing "testname" and the value "testvalue" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should not exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "mytestname" and value "othervalue"
    Then a cookie with a name containing "testname" and the value "testvalue" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and the value :value should not exist" fails with an error when the cookie exists with matching name and value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "mytestname" and value "testvalue"
      Then a cookie with a name containing "testname" and the value "testvalue" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "testname" was set with value "testvalue", but it should not be "testvalue".
      """

  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should not exist" works as expected
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with a name containing "testname" and a value containing "testval" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should not exist" works as expected with real browser
    When I visit "/sites/default/files/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with a name containing "testname" and a value containing "testval" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and a value containing :partial_value should not exist" fails with an error when the cookie exists with matching partial name and value
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/cookies.html"
      When I set a test cookie with name "mytestname" and value "mytestvalue"
      Then a cookie with a name containing "testname" and a value containing "testval" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "testname" was set with value containing "mytestvalue", but it should not contain "testval".
      """
