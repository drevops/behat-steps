Feature: Check that LinkTrait works

  @d7 @d9
  Scenario: Assert link with href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org"

  @d7 @d9
  Scenario: Assert link with href with locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org" in "#block-system-powered-by,#block-bartik-powered,.block-system-powered-by-block"

  @d7 @d9
  Scenario: Assert link with wildcard in href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal*"

  @d9
  Scenario: Assert link with href without locator does not exist
    Given I go to "/"
    Then I should not see the link "RandomLinkText" with "https://www.drupal.org"
    Then I should not see the link "Drupal" with "https://www.randomhref.org"

  @d9
  Scenario: Assert link with href with locator does not exist
    Given I go to "/"
    Then I should not see the link "RandomLinkText" with "https://www.randomhref.org" in "#block-system-powered-by,#block-bartik-powered"
    Then I should not see the link "Drupal" with "https://www.drupal.org" in "#random-locator"

  @d9
  Scenario: Assert link with wildcard in href without locator does not exist
    Given I go to "/"
    Then I should not see the link "Drupal" with "https://www.randomhref*"

  @api @d9
  Scenario: Assert link with title
    Given I am logged in as a user with the "administrator" role
    When I go to "/"
    Then the link with title "Return to site content" exists
    And the link with title "Some non-existing title" does not exist
    And I click the link with title "Return to site content"

  @api @d9
  Scenario: Assert link is absolute or not
    Given I am logged in as a user with the "administrator" role
    When I go to "/"
    Then the link with title "Drupal" is an absolute link
    Then the link with title "Return to site content" is not an absolute link

  @trait:LinkTrait @d9
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I go to "/"
      Then I click the link with title "Some non-existing title"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with title "Some non-existing title" does not exist.
      """

  @trait:LinkTrait @d9
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I go to "/"
      Then the link with title "Return to site content" does not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with title "Return to site content" exists, but should not.
      """
