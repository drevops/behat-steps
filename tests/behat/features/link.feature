Feature: Check that LinkTrait works
  As Behat Steps library developer
  I want to provide tools to verify link attributes and behaviors
  So that users can test navigation elements reliably

  Scenario: Assert link with href without selector
    When I visit "/sites/default/files/links.html"
    Then the link "Absolute Link One" with the href "https://www.example.com" should exist

  Scenario: Assert link with href with selector
    When I visit "/sites/default/files/links.html"
    Then the link "Link in navigation" with the href "https://www.example.com" within the element "#navigation" should exist

  Scenario: Assert link with wildcard in href without selector
    When I visit "/sites/default/files/links.html"
    Then the link "Absolute Link Two" with the href "https://www.example*" should exist

  Scenario: Assert link with href without selector does not exist
    When I visit "/sites/default/files/links.html"
    Then the link "RandomLinkText" with the href "https://www.example.com" should not exist
    And the link "Absolute Link One" with the href "https://www.randomhref.org" should not exist

  Scenario: Assert link with href with selector does not exist
    When I visit "/sites/default/files/links.html"
    Then the link "RandomLinkText" with the href "https://www.randomhref.org" within the element "#navigation" should not exist
    And the link "Absolute Link One" with the href "https://www.example.com" within the element "#random-selector" should not exist

  Scenario: Assert link with wildcard in href without selector does not exist
    When I visit "/sites/default/files/links.html"
    Then the link "Absolute Link One" with the href "https://www.randomhref*" should not exist

  Scenario: Assert link with title
    When I visit "/sites/default/files/links.html"
    Then the link with the title "Link title one" should exist
    And the link with the title "Some non-existing title" should not exist
    And I click on the link with the title "Link title one"

  Scenario: Assert link is absolute or not
    When I visit "/sites/default/files/links.html"
    Then the link "Absolute Link One" should be an absolute link
    And the link "Relative Link One" should not be an absolute link

  @trait:LinkTrait
  Scenario: Assert that negative assertion for "I click the link with title :title" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      When I click on the link with the title "Some non-existing title"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Link with title "Some non-existing title" not found.
      """

  @trait:LinkTrait
  Scenario: Assert that "the link with title :title exists" fails when link not found
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link with the title "Nonexistent title" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Link with title "Nonexistent title" not found.
      """

  @trait:LinkTrait
  Scenario: Assert that negative assertion for "the link with title :title exists" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link with the title "Link title one" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with the title "Link title one" exists, but should not.
      """

  @trait:LinkTrait
  Scenario: Assert that link with href fails when selector does not exist
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "Absolute Link One" with the href "https://www.example.com" within the element "#nonexistent-selector" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-selector" not found.
      """

  @trait:LinkTrait
  Scenario: Assert that link with href fails when link text not found
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "NonexistentLinkText" with the href "https://www.example.com" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Link with text "NonexistentLinkText" not found.
      """

  @trait:LinkTrait
  Scenario: Assert that link with href fails when href does not match
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "Absolute Link One" with the href "https://wrong.url" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link href "https://www.example.com" does not match the specified href "https://wrong.url"
      """

  @trait:LinkTrait
  Scenario: Assert that negative assertion fails when link href matches but should not
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "Absolute Link One" with the href "https://www.example.com" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link href "https://www.example.com" matches the specified href "https://www.example.com" but should not
      """

  @trait:LinkTrait
  Scenario: Assert that absolute link check fails when link not found
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "NonexistentLink" should be an absolute link
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Link with text "NonexistentLink" not found.
      """

  @trait:LinkTrait
  Scenario: Assert that absolute link check fails when link is not absolute
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "Relative Link One" should be an absolute link
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link "Relative Link One" is not an absolute link.
      """

  @trait:LinkTrait
  Scenario: Assert that not absolute link check fails when link not found
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "NonexistentLink" should not be an absolute link
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Link with text "NonexistentLink" not found.
      """

  @trait:LinkTrait
  Scenario: Assert that not absolute link check fails when link is absolute
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/links.html"
      Then the link "Absolute Link One" should not be an absolute link
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link "Absolute Link One" is an absolute link.
      """
