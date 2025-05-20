Feature: Check that ContentTrait works
  As Behat Steps library developer
  I want to provide tools to manage Drupal content programmatically
  So that users can test content functionality reliably

  @api
  Scenario: Assert "@Given the content type :content_type does not exist" works as expected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/types/add"
    And I fill in "Name" with "test_content_type"
    And I fill in "Machine-readable name" with "test_content_type"
    And I press "Save"
    And I visit "/admin/structure/types"
    Then I should see "test_content_type"
    When the content type "test_content_type" does not exist
    And I visit "/admin/structure/types"
    Then I should not see "test_content_type"

  @api
  Scenario: Assert "@Given the content type :content_type does not exist" works as expected on non-existing content type
    Given the content type "test_content_type" does not exist
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/types"
    Then I should not see "test_content_type"

  @api
  Scenario: Assert "@Given the following :content_type content does not exist:" works as expected
    Given page content:
      | title              |
      | [TEST] Page title1 |
      | [TEST] Page title2 |
    And I am logged in as a user with the "administrator" role
    When I go to "content/test-page-title1"
    Then I should get a 200 HTTP response
    When I go to "content/test-page-title2"
    Then I should get a 200 HTTP response
    When the following "page" content does not exist:
      | title              |
      | [TEST] Page title1 |
      | [TEST] Page title2 |
    And I go to "content/test-page-title1"
    Then I should get a 404 HTTP response
    When I go to "content/test-page-title2"
    Then I should get a 404 HTTP response

  @api
  Scenario: Assert "When I visit the :content_type content page with the title :title" works as expected
    Given page content:
      | title             |
      | [TEST] Page title |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content page with the title "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content page with the title :title" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "non_existing" content page with the title "[TEST] Page title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Content type "non_existing" does not exist.
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content page with the title :title" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "page" content page with the title "[TEST] Non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """

  @api
  Scenario: Assert "When I visit the :content_type content edit page with the title :title" works as expected
    Given page content:
      | title             |
      | [TEST] Page title |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content edit page with the title "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content edit page with the title :title" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "non_existing" content edit page with the title "[TEST] Page title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Content type "non_existing" does not exist.
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content edit page with the title :title" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "page" content edit page with the title "[TEST] Non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """

  @api
  Scenario: Assert "When I visit the :content_type content delete page with the title :title" works as expected
    Given page content:
      | title             |
      | [TEST] Page title |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content delete page with the title "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content delete page with the title :title" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "non_existing" content delete page with the title "[TEST] Page title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Content type "non_existing" does not exist.
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content delete page with the title :title" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "page" content delete page with the title "[TEST] Non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """

  @api
  Scenario: Assert "When I visit the :content_type content scheduled transitions page with the title :title" works as expected
    Given page content:
      | title             |
      | [TEST] Page title |
    And I am logged in as a user with the "administrator" role
    When I visit the "page" content scheduled transitions page with the title "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content scheduled transitions page with the title :title" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "non_existing" content scheduled transitions page with the title "[TEST] Page title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Content type "non_existing" does not exist.
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content scheduled transitions page with the title :title" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "page" content scheduled transitions page with the title "[TEST] Non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """

  @api
  Scenario: Assert "When I change the moderation state of the :content_type content with the title :title to the :new_state state" works as expected
    Given page content:
      | title             | moderation_state |
      | [TEST] Page title | draft            |
    And I am an anonymous user
    When I visit the "page" content page with the title "[TEST] Page title"
    Then the response status code should be 403
    When I change the moderation state of the "page" content with the title "[TEST] Page title" to the "published" state
    And I visit the "page" content page with the title "[TEST] Page title"
    Then the response status code should be 200

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I change the moderation state of the :content_type content with the title :title to the :new_state state" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I change the moderation state of the "non_existing" content with the title "[TEST] Page title" to the "published" state
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Content type "non_existing" does not exist.
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I change the moderation state of the :content_type content with the title :title to the :new_state state" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I change the moderation state of the "page" content with the title "[TEST] Non-existing" to the "published" state
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I change the moderation state of the :content_type content with the title :title to the :new_state state" works as expected for a node without moderation state enabled
    Given some behat configuration
    And scenario steps:
      """
      Given landing_page content:
        | title             |
        | [TEST] Page title |
      Given I am logged in as a user with the "administrator" role
      When I change the moderation state of the "landing_page" content with the title "[TEST] Page title" to the "published" state
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      State "published" is not defined in the workflow for "landing_page" content type.
      """

  @api
  Scenario: Assert "When I visit the revisions page for the :content_type titled :title" works as expected
    Given article content:
      | title                | body        |
      | [TEST] Article title | First draft |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Article title"
    And I fill in "Body" with "Updated content"
    And I press "Save"
    When I visit the revisions page for the "article" titled "[TEST] Article title"
    Then I should see "[TEST] Article title"
    And I should see "Revisions"
    And I should see "Current revision"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the revisions page for the :content_type titled :title" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the revisions page for the "non_existing" titled "[TEST] Article title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Content type "non_existing" does not exist.
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the revisions page for the :content_type titled :title" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the revisions page for the "article" titled "[TEST] Non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "article" content with title "[TEST] Non-existing".
      """
