Feature: Check that CookieTrait works

  Scenario: Assert step definition "a cookie with( the) name :name should exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name "testname" should exist
    And a cookie with the name "testname" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name should exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name "testname" should exist
    And a cookie with the name "testname" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then a cookie with name "testname" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was not set (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name :name and value :value should exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name "testname" and value "testvalue" should exist
    And a cookie with the name "testname" and value "testvalue" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name and value :value should exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name "testname" and value "testvalue" should exist
    And a cookie with the name "testname" and value "testvalue" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value :value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then a cookie with name "testname" and value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was not set (Exception)
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value :value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testothername" and value "testothervalue"
      Then a cookie with name "testothername" and value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testothername" was set with value "testothervalue", but it should be "testvalue" (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name :name and value containing :partial_value should exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name "testname" and value containing "estva" should exist
    And a cookie with the name "testname" and value containing "estva" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name and value containing :partial_value should exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name "testname" and value containing "estva" should exist
    And a cookie with the name "testname" and value containing "estva" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value containing :partial_value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with name "testname" and value containing "estva" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was not set (Exception)
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value containing :partial_value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testname" and value "testothervalue"
      Then a cookie with name "testname" and value containing "estva" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set with value "testothervalue", but it should contain "estva" (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name containing :partial_name should exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name containing "estna" should exist
    And a cookie with the name containing "estna" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name containing :partial_name should exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name containing "estna" should exist
    And a cookie with the name containing "estna" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with name containing "estna" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was not set (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value :value should exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name containing "estna" and value "testvalue" should exist
    And a cookie with the name containing "estna" and value "testvalue" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value :value should exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name containing "estna" and value "testvalue" should exist
    And a cookie with the name containing "estna" and value "testvalue" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name and value :value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with name containing "estna" and value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was not set (Exception)
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name and value :value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testname" and value "prefixtestvaluesuffix"
      Then a cookie with name containing "estna" and value "testvalue" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was set with value "prefixtestvaluesuffix", but it should be "testvalue" (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value containing :partial_value should exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name containing "estna" and value containing "estval" should exist
    And a cookie with the name containing "estna" and value containing "estval" should exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value containing :partial_value should exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "testvalue"
    Then a cookie with name containing "estna" and value containing "estval" should exist
    And a cookie with the name containing "estna" and value containing "estval" should exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name and value containing :partial_value should exist" fails with an error for incorrect name
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testothername" and value "testvalue"
      Then a cookie with name containing "estna" and value containing "estval" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was not set (Exception)
      """

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name and value containing :partial_value should exist" fails with an error for incorrect value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testname" and value "testothervalue"
      Then a cookie with name containing "estna" and value containing "estval" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "estna" was set with value "testothervalue", but it should contain "estval" (Exception)
      """

  #
  # NOT EXISTS
  #

  Scenario: Assert step definition "a cookie with( the) name :name should not exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with name "testname" should not exist
    And a cookie with the name "testname" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name should not exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with name "testname" should not exist
    And a cookie with the name "testname" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name should not exist" fails with an error when the cookie exists
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testname" and value "testvalue"
      Then a cookie with name "testname" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set but it should not be (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name :name and value :value should not exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with name "testname" and value "testvalue" should not exist
    And a cookie with the name "testname" and value "testvalue" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name and value :value should not exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with name "testname" and value "testvalue" should not exist
    And a cookie with the name "testname" and value "testvalue" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value :value should not exist" fails with an error when the cookie exists with the specified value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testname" and value "testvalue"
      Then a cookie with name "testname" and value "testvalue" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set with value "testvalue", but it should not be "testvalue" (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name :name and value containing :partial_value should not exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with name "testname" and value containing "testval" should not exist
    And a cookie with the name "testname" and value containing "testval" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name :name and value containing :partial_value should not exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "testname" and value "othervalue"
    Then a cookie with name "testname" and value containing "testval" should not exist
    And a cookie with the name "testname" and value containing "testval" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name :name and value containing :partial_value should not exist" fails with an error when the cookie exists with a value containing the partial value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "testname" and value "testvalue"
      Then a cookie with name "testname" and value containing "testval" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name "testname" was set with value containing "testvalue", but it should not contain "testval" (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name containing :partial_name should not exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "othername" and value "testvalue"
    Then a cookie with name containing "testname" should not exist
    And a cookie with the name containing "testname" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name containing :partial_name should not exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "othername" and value "testvalue"
    Then a cookie with name containing "testname" should not exist
    And a cookie with the name containing "testname" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name should not exist" fails with an error when the cookie exists with a name containing the partial name
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "mytestname" and value "testvalue"
      Then a cookie with name containing "testname" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "testname" was set but it should not be (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value :value should not exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "mytestname" and value "othervalue"
    Then a cookie with name containing "testname" and value "testvalue" should not exist
    And a cookie with the name containing "testname" and value "testvalue" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value :value should not exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "mytestname" and value "othervalue"
    Then a cookie with name containing "testname" and value "testvalue" should not exist
    And a cookie with the name containing "testname" and value "testvalue" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name and value :value should not exist" fails with an error when the cookie exists with matching name and value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "mytestname" and value "testvalue"
      Then a cookie with name containing "testname" and value "testvalue" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "testname" was set with value "testvalue", but it should not be "testvalue" (Exception)
      """

  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value containing :partial_value should not exist" works as expected
    Given I visit "/"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with name containing "testname" and value containing "testval" should not exist
    And a cookie with the name containing "testname" and value containing "testval" should not exist

  @javascript
  Scenario: Assert step definition "a cookie with( the) name containing :partial_name and value containing :partial_value should not exist" works as expected with real browser
    Given I visit "/"
    When I set a test cookie with name "othername" and value "othervalue"
    Then a cookie with name containing "testname" and value containing "testval" should not exist
    And a cookie with the name containing "testname" and value containing "testval" should not exist

  @trait:CookieTrait
  Scenario: Assert that negative assertion for "a cookie with( the) name containing :partial_name and value containing :partial_value should not exist" fails with an error when the cookie exists with matching partial name and value
    Given some behat configuration
    And scenario steps:
      """
      Given I visit "/"
      When I set a test cookie with name "mytestname" and value "mytestvalue"
      Then a cookie with name containing "testname" and value containing "testval" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The cookie with name containing "testname" was set with value containing "mytestvalue", but it should not contain "testval"
      """
