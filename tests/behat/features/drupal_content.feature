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
    Given the following page content:
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
    Given the following page content:
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
    Given the following page content:
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
    Given the following page content:
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
    Given the following page content:
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
    Given the following page content:
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
      Given the following landing_page content:
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
  Scenario: Assert "When I visit the :content_type content revisions page with the title :title" works as expected
    Given the following article content:
      | title                | body        |
      | [TEST] Article title | First draft |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Article title"
    And I fill in "Body" with "Updated content"
    And I press "Save"
    When I visit the "article" content revisions page with the title "[TEST] Article title"
    Then I should see "[TEST] Article title"
    And I should see "Revisions"
    And I should see "Current revision"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I visit the :content_type content revisions page with the title :title" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "article" content edit page with the title "[TEST] No existing title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "article" content with title "[TEST] No existing title".
      """

  @api
  Scenario: Create single node with vertical field format
    Given I am logged in as a user with the "administrator" role
    And the following page content with fields:
      | title  | [TEST] Vertical Page    |
      | body   | Vertical format content |
      | status | 1                       |
    When I go to "/admin/content"
    Then I should see "[TEST] Vertical Page"

  @api
  Scenario: Create multiple nodes with vertical field format
    Given I am logged in as a user with the "administrator" role
    And the following page content with fields:
      | title  | [TEST] V-Page 1    | [TEST] V-Page 2     | [TEST] V-Page 3    |
      | body   | First page content | Second page content | Third page content |
      | status | 1                  | 1                   | 1                  |
    When I go to "/admin/content"
    Then I should see "[TEST] V-Page 1"
    And I should see "[TEST] V-Page 2"
    And I should see "[TEST] V-Page 3"

  @api
  Scenario: Assert "Then :content_type content with the title :title should not exist" works as expected
    Given I am logged in as a user with the "administrator" role
    Then "page" content with the title "[TEST] Non-existing page" should not exist

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "Then :content_type content with the title :title should not exist" works as expected when content exists
    Given some behat configuration
    And scenario steps:
      """
      Given the following page content:
        | title              |
        | [TEST] Exists page |
      Then "page" content with the title "[TEST] Exists page" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      "page" content with the title "[TEST] Exists page" should not exist, but it does (nid:
      """

  @api
  Scenario: Assert "When I rebuild the access grants for the :content_type content with the title :title" works as expected
    Given the following page content:
      | title                    |
      | [TEST] Grants page title |
    And I am logged in as a user with the "administrator" role
    When I rebuild the access grants for the "page" content with the title "[TEST] Grants page title"
    And I visit the "page" content page with the title "[TEST] Grants page title"
    Then I should see "[TEST] Grants page title"

  @api
  Scenario: Assert "When I rebuild the access grants for all content" works as expected
    Given the following page content:
      | title                        |
      | [TEST] Grants all page title |
    And I am logged in as a user with the "administrator" role
    When I rebuild the access grants for all content
    And I visit the "page" content page with the title "[TEST] Grants all page title"
    Then I should see "[TEST] Grants all page title"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I rebuild the access grants for the :content_type content with the title :title" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I rebuild the access grants for the "page" content with the title "[TEST] Non-existing"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """

  @api
  Scenario: Assert file field on node resolves bare fixture filename without explicit managed file
    Given the following article content:
      | title                | field_file |
      | [TEST] Fixture file  | text.txt   |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Fixture file"
    Then I should see "[TEST] Fixture file"
    And the response should contain ".txt"

  @api
  Scenario: Assert image field on node resolves bare fixture filename without explicit managed file
    Given the following article content:
      | title                 | field_image |
      | [TEST] Fixture image  | image.png   |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Fixture image"
    Then I should see "[TEST] Fixture image"

  @api
  Scenario: Assert file field on node resolves compound fixture filename without explicit managed file
    Given the following article content:
      | title                         | field_file                                       |
      | [TEST] Compound fixture file  | target_id:"text.txt", description:"My document"  |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Compound fixture file"
    Then I should see "[TEST] Compound fixture file"
    And the response should contain ".txt"

  @api
  Scenario: Assert image field on node resolves compound fixture filename without explicit managed file
    Given the following article content:
      | title                          | field_image                              |
      | [TEST] Compound fixture image  | target_id:"image.png", alt:"My image"    |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Compound fixture image"
    Then I should see "[TEST] Compound fixture image"

  @api
  Scenario: Assert file field on node resolves fixture path in a subdirectory
    Given the following article content:
      | title                          | field_file          |
      | [TEST] Subdirectory file       | subdir/document.pdf |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Subdirectory file"
    Then I should see "[TEST] Subdirectory file"
    And the response should contain ".pdf"

  @api
  Scenario: Assert file field on node resolves compound fixture path in a subdirectory
    Given the following article content:
      | title                            | field_file                                              |
      | [TEST] Compound subdirectory file | target_id:"subdir/document.pdf", description:"My doc"  |
    And I am logged in as a user with the "administrator" role
    When I visit the "article" content edit page with the title "[TEST] Compound subdirectory file"
    Then I should see "[TEST] Compound subdirectory file"
    And the response should contain ".pdf"

  @api
  Scenario: Assert "When I set the path alias of the :content_type content with the title :title to :alias" works as expected
    Given the following page content:
      | title                   |
      | [TEST] Alias page title |
    And I am logged in as a user with the "administrator" role
    When I set the path alias of the "page" content with the title "[TEST] Alias page title" to "/test-custom-alias"
    And I go to "test-custom-alias"
    Then I should get a 200 HTTP response
    And I should see "[TEST] Alias page title"

  @api
  Scenario: Assert "When I set the path alias of the :content_type content with the title :title to :alias" works as expected for an alias without a leading slash
    Given the following page content:
      | title                            |
      | [TEST] Alias no slash page title |
    And I am logged in as a user with the "administrator" role
    When I set the path alias of the "page" content with the title "[TEST] Alias no slash page title" to "test-no-slash-alias"
    And I go to "test-no-slash-alias"
    Then I should get a 200 HTTP response
    And I should see "[TEST] Alias no slash page title"

  @api
  Scenario: Assert "When I set the path alias of the :content_type content with the title :title to :alias" replaces an existing alias instead of adding a second one
    Given the following page content:
      | title                            |
      | [TEST] Alias replaced page title |
    And I am logged in as a user with the "administrator" role
    When I set the path alias of the "page" content with the title "[TEST] Alias replaced page title" to "/test-alias-first"
    And I go to "test-alias-first"
    Then I should get a 200 HTTP response
    When I set the path alias of the "page" content with the title "[TEST] Alias replaced page title" to "/test-alias-second"
    And I go to "test-alias-second"
    Then I should get a 200 HTTP response
    And the path should be "/test-alias-second"
    When I go to "test-alias-first"
    Then the path should be "/test-alias-second"

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I set the path alias of the :content_type content with the title :title to :alias" works as expected for non-existing content type
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I set the path alias of the "non_existing" content with the title "[TEST] Page title" to "/test-alias"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Content type "non_existing" does not exist.
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I set the path alias of the :content_type content with the title :title to :alias" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I set the path alias of the "page" content with the title "[TEST] Non-existing" to "/test-alias"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "When I set the path alias of the :content_type content with the title :title to :alias" works as expected for an empty alias
    Given some behat configuration
    And scenario steps:
      """
      Given the following page content:
        | title                         |
        | [TEST] Empty alias page title |
      When I set the path alias of the "page" content with the title "[TEST] Empty alias page title" to ""
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Path alias for "page" content with the title "[TEST] Empty alias page title" cannot be empty.
      """

  @api
  Scenario: Assert "Then :content_type content with the title :title should be published" works as expected
    Given the following page content:
      | title                       | moderation_state |
      | [TEST] Published page title | published        |
    Then "page" content with the title "[TEST] Published page title" should be published

  @api
  Scenario: Assert "Then :content_type content with the title :title should not be published" works as expected
    Given the following page content:
      | title                         | moderation_state |
      | [TEST] Unpublished page title | draft            |
    Then "page" content with the title "[TEST] Unpublished page title" should not be published

  @api
  Scenario: Assert publish state assertions resolve the most recently created content when titles are duplicated
    Given the following page content:
      | title                       | moderation_state |
      | [TEST] Duplicate page title | published        |
      | [TEST] Duplicate page title | draft            |
    Then "page" content with the title "[TEST] Duplicate page title" should not be published

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "Then :content_type content with the title :title should be published" works as expected when content is not published
    Given some behat configuration
    And scenario steps:
      """
      Given the following page content:
        | title                         | moderation_state |
        | [TEST] Unpublished page title | draft            |
      Then "page" content with the title "[TEST] Unpublished page title" should be published
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      "page" content with the title "[TEST] Unpublished page title" should be published, but it is not (nid:
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "Then :content_type content with the title :title should not be published" works as expected when content is published
    Given some behat configuration
    And scenario steps:
      """
      Given the following page content:
        | title                       | moderation_state |
        | [TEST] Published page title | published        |
      Then "page" content with the title "[TEST] Published page title" should not be published
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      "page" content with the title "[TEST] Published page title" should not be published, but it is (nid:
      """

  @trait:Drupal\ContentTrait
  Scenario: Assert negative "Then :content_type content with the title :title should be published" works as expected for non-existing content
    Given some behat configuration
    And scenario steps:
      """
      Then "page" content with the title "[TEST] Non-existing" should be published
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "page" content with title "[TEST] Non-existing".
      """
