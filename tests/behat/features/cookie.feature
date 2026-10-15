Feature: Check that CookieTrait works
  As Behat Steps library developer
  I want to provide tools to verify browser cookies and their values
  So that users can test session management and user preferences

  @phpserver
  Scenario: Assert step definition "the cookie :name should exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    And I set a test cookie with name "testname" and value "testvalue"
    Then the cookie "testname" should exist
    And the cookie "testname" should exist

  @javascript @phpserver
  Scenario: Assert step definition "the cookie :name should exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    And I set a test cookie with name "testname" and value "testvalue"
    Then the cookie "testname" should exist
    And the cookie "testname" should exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name should exist" fails with an error
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then the cookie "testname" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testname" was not set.
      """

  @phpserver
  Scenario: Assert step definition "the cookie :name with the value :value should exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then the cookie "testname" with the value "testvalue" should exist
    And the cookie "testname" with the value "testvalue" should exist

  @javascript @phpserver
  Scenario: Assert step definition "the cookie :name with the value :value should exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then the cookie "testname" with the value "testvalue" should exist
    And the cookie "testname" with the value "testvalue" should exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name with the value :value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then the cookie "testname" with the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testname" was not set.
      """

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name with the value :value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then the cookie "testothername" with the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testothername" was set with the value "testothervalue", but it should be "testvalue".
      """

  @phpserver
  Scenario: Assert step definition "the cookie :name with a value containing :partial_value should exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then the cookie "testname" with a value containing "estva" should exist
    And the cookie "testname" with a value containing "estva" should exist

  @javascript @phpserver
  Scenario: Assert step definition "the cookie :name with a value containing :partial_value should exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then the cookie "testname" with a value containing "estva" should exist
    And the cookie "testname" with a value containing "estva" should exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name with a value containing :partial_value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then the cookie "testname" with a value containing "estva" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testname" was not set.
      """

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name with a value containing :partial_value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testname" and value "testothervalue"
      Then the cookie "testname" with a value containing "estva" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testname" was set with the value "testothervalue", but it should contain "estva".
      """

  @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name should exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" should exist

  @javascript @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name should exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" should exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with a name containing "estna" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No cookie with a name containing "estna" was set.
      """

  @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and the value "testvalue" should exist

  @javascript @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and the value "testvalue" should exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and the value :value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with a name containing "estna" and the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No cookie with a name containing "estna" was set.
      """

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and the value :value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testname" and value "prefixtestvaluesuffix"
      Then a cookie with a name containing "estna" and the value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A cookie with a name containing "estna" was set with the value "prefixtestvaluesuffix", but it should be "testvalue".
      """

  @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and a value containing "estval" should exist

  @javascript @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with a name containing "estna" and a value containing "estval" should exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and a value containing :partial_value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with a name containing "estna" and a value containing "estval" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No cookie with a name containing "estna" was set.
      """

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and a value containing :partial_value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testname" and value "testothervalue"
      Then a cookie with a name containing "estna" and a value containing "estval" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A cookie with a name containing "estna" was set with the value "testothervalue", but it should contain "estval".
      """

  @phpserver
  Scenario: Assert step definition "the cookie :name should not exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then the cookie "testname" should not exist

  @javascript @phpserver
  Scenario: Assert step definition "the cookie :name should not exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then the cookie "testname" should not exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name should not exist" fails with an error when the cookie exists
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testname" and value "testvalue"
      Then the cookie "testname" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testname" was set, but it should not be.
      """

  @phpserver
  Scenario: Assert step definition "the cookie :name with the value :value should not exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then the cookie "testname" with the value "testvalue" should not exist

  @javascript @phpserver
  Scenario: Assert step definition "the cookie :name with the value :value should not exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then the cookie "testname" with the value "testvalue" should not exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name with the value :value should not exist" fails with an error when the cookie exists with the specified value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testname" and value "testvalue"
      Then the cookie "testname" with the value "testvalue" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testname" was set with the value "testvalue", but it should not be "testvalue".
      """

  @phpserver
  Scenario: Assert step definition "the cookie :name with a value containing :partial_value should not exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then the cookie "testname" with a value containing "testval" should not exist

  @javascript @phpserver
  Scenario: Assert step definition "the cookie :name with a value containing :partial_value should not exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "testname" and value "othervalue"
    Then the cookie "testname" with a value containing "testval" should not exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "the cookie :name with a value containing :partial_value should not exist" fails with an error when the cookie exists with a value containing the partial value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "testname" and value "testvalue"
      Then the cookie "testname" with a value containing "testval" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie "testname" was set with the value "testvalue", but it should not contain "testval".
      """

  @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name should not exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "othername" and value "testvalue"
    Then a cookie with a name containing "testname" should not exist

  @javascript @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name should not exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "othername" and value "testvalue"
    Then a cookie with a name containing "testname" should not exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name should not exist" fails with an error when the cookie exists with a name containing the partial name
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "mytestname" and value "testvalue"
      Then a cookie with a name containing "testname" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A cookie with a name containing "testname" was set, but it should not be.
      """

  @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should not exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "mytestname" and value "othervalue"
    Then a cookie with a name containing "testname" and the value "testvalue" should not exist

  @javascript @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and the value :value should not exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "mytestname" and value "othervalue"
    Then a cookie with a name containing "testname" and the value "testvalue" should not exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and the value :value should not exist" fails with an error when the cookie exists with matching name and value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "mytestname" and value "testvalue"
      Then a cookie with a name containing "testname" and the value "testvalue" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A cookie with a name containing "testname" was set with the value "testvalue", but it should not be "testvalue".
      """

  @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should not exist" works as expected
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with a name containing "testname" and a value containing "testval" should not exist

  @javascript @phpserver
  Scenario: Assert step definition "a cookie with a name containing :partial_name and a value containing :partial_value should not exist" works as expected with real browser
    When I visit "http://cli:8888/cookies.html"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with a name containing "testname" and a value containing "testval" should not exist

  @test-trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with a name containing :partial_name and a value containing :partial_value should not exist" fails with an error when the cookie exists with matching partial name and value
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/cookies.html"
      When I set a test cookie with name "mytestname" and value "mytestvalue"
      Then a cookie with a name containing "testname" and a value containing "testval" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A cookie with a name containing "testname" was set with the value "mytestvalue", but it should not contain "testval".
      """
