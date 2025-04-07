Feature: Check that LinkTrait works

  Scenario: Assert link with href without locator
    Given I go to "/"
    Then the link "Drupal" with the href "https://www.drupal.org" should exist

  Scenario: Assert link with href with locator
    Given I go to "/"
    Then the link "Drupal" with the href "https://www.drupal.org" within the element "#block-system-powered-by,#block-bartik-powered,.block-system-powered-by-block" should exist

  Scenario: Assert link with wildcard in href without locator
    Given I go to "/"
    Then the link "Drupal" with the href "https://www.drupal*" should exist

  Scenario: Assert link with href without locator does not exist
    Given I go to "/"
    Then the link "RandomLinkText" with the href "https://www.drupal.org" should not exist
    Then the link "Drupal" with the href "https://www.randomhref.org" should not exist

  Scenario: Assert link with href with locator does not exist
    Given I go to "/"
    Then the link "RandomLinkText" with the href "https://www.randomhref.org" within the element "#block-system-powered-by,#block-bartik-powered" should not exist
    Then the link "Drupal" with the href "https://www.drupal.org" within the element "#random-locator" should not exist

  Scenario: Assert link with wildcard in href without locator does not exist
    Given I go to "/"
    Then the link "Drupal" with the href "https://www.randomhref*" should not exist

  @api
  Scenario: Assert link with title
    Given I am logged in as a user with the "administrator" role
    When I go to "/"
    Then the link with the title "Return to site content" should exist
    And the link with the title "Some non-existing title" should not exist
    And I click on the link with the title "Return to site content"

  @api
  Scenario: Assert link is absolute or not
    Given I am logged in as a user with the "administrator" role
    When I go to "/"
    Then the link "Drupal" should be an absolute link
    And the link "Return to site content" should not be an absolute link

  @trait:LinkTrait
  Scenario: Assert that negative assertion for "I click the link with title :title" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I go to "/"
      When I click on the link with the title "Some non-existing title"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with the title "Some non-existing title" does not exist.
      """

  @trait:LinkTrait
  Scenario: Assert that negative assertion for "the link with title :title exists" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I go to "/"
      Then the link with the title "Return to site content" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with the title "Return to site content" exists, but should not.
      """
