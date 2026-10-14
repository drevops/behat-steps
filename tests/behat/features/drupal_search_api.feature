@search
Feature: Check that SearchApiTrait works
  As Behat Steps library developer
  I want to provide tools to index and search content
  So that users can test search functionality

  Scenario: Assert "When the :content_type content with the title :title is indexed" works as expected
    When search indexing runs for 10 items
    And the following article content exists:
      | title                                        | moderation_state |
      | [MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT | published        |
      | [MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT     | draft            |
    And I am logged in as a user with the role "administrator"
    And I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should not see "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT"
    And I should not see "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT"

    When the "article" content with the title "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT" is indexed
    And the "article" content with the title "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT" is indexed

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    Then I should see "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT"
    And I should not see "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT"

  Scenario: Assert "When the :content_type content with the title :title is indexed" indexes only the named content
    Given the following article content exists:
      | title                                      | moderation_state |
      | [MYTEST] QUEUEDARTICLE TESTUNIQUEQUEUETEXT | published        |
      | [MYTEST] NAMEDARTICLE TESTUNIQUEQUEUETEXT  | published        |
    And I am logged in as a user with the role "administrator"
    When the "article" content with the title "[MYTEST] NAMEDARTICLE TESTUNIQUEQUEUETEXT" is indexed

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUEQUEUETEXT"
    And I press "edit-submit-search"
    Then I should see "[MYTEST] NAMEDARTICLE TESTUNIQUEQUEUETEXT"
    And I should not see "[MYTEST] QUEUEDARTICLE TESTUNIQUEQUEUETEXT"

  @testmode
  Scenario: Assert "When the :content_type content with the title :title is indexed" works as expected with test mode
    Given the following article content exists:
      | title                           | moderation_state |
      | TESTPUBLISHEDARTICLE 1          | published        |
      | [MYTEST] TESTPUBLISHEDARTICLE 2 | published        |
    And I am logged in as a user with the role "administrator"
    When the "article" content with the title "TESTPUBLISHEDARTICLE 1" is indexed
    And the "article" content with the title "[MYTEST] TESTPUBLISHEDARTICLE 2" is indexed

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTPUBLISHEDARTICLE"
    And I press "edit-submit-search"
    Then I should not see "TESTPUBLISHEDARTICLE 1"
    And I should see "[MYTEST] TESTPUBLISHEDARTICLE 2"

  Scenario: Assert "When search indexing runs for :count item(s)" works as expected
    Given the following article content exists:
      | title                                     | moderation_state |
      | [MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT | published        |
      | [MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT | published        |
      | [MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT | draft            |
    And I am logged in as a user with the role "administrator"

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should not see "[MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT"
    And I should not see "[MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT"
    And I should not see "[MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT"

    When search indexing runs for 1 item

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should see "[MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT"
    And I should not see "[MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT"
    And I should not see "[MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT"

    When search indexing runs for 2 items

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should see "[MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT"
    And I should see "[MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT"
    And I should not see "[MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT"

  @test-trait:Drupal\SearchApiTrait
  Scenario: Assert "When the :content_type content with the title :title is indexed" fails when content not found
    Given some behat configuration
    And scenario steps:
      """
      When the "article" content with the title "Non-existent article" is indexed
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "article" page "Non-existent article".
      """

  @test-trait:Drupal\SearchApiTrait
  Scenario: Assert "When search indexing runs for :count item(s)" fails when the count is not an integer
    Given some behat configuration
    And scenario steps:
      """
      When search indexing runs for "all" items
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The count must be an integer, but "all" was given.
      """

  Scenario: Assert "When the Search API cron runs" works as expected
    Given the following article content exists:
      | title                                    | moderation_state |
      | [MYTEST] CRONARTICLE1 TESTUNIQUECRONTEXT | published        |
      | [MYTEST] CRONARTICLE2 TESTUNIQUECRONTEXT | published        |
    And I am logged in as a user with the role "administrator"

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUECRONTEXT"
    And I press "edit-submit-search"
    Then I should not see "[MYTEST] CRONARTICLE1 TESTUNIQUECRONTEXT"
    And I should not see "[MYTEST] CRONARTICLE2 TESTUNIQUECRONTEXT"

    When the Search API cron runs

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUECRONTEXT"
    And I press "edit-submit-search"
    Then I should see "[MYTEST] CRONARTICLE1 TESTUNIQUECRONTEXT"
    And I should see "[MYTEST] CRONARTICLE2 TESTUNIQUECRONTEXT"

  @module:!search_api_solr
  Scenario: Assert "When the Search API Solr cron runs" is a no-op when Solr module is not enabled
    When the Search API Solr cron runs

  # Indexing a number of items resolves the search index capability, so
  # '@backend:drush' runs it over Drush.
  @backend:drush
  Scenario: Assert "When search indexing runs for :count item(s)" indexes the items over Drush
    When search indexing runs for 10 items
    And the following article content exists:
      | title                                       | moderation_state |
      | [MYTEST] DRUSHINDEXARTICLE1 TESTUNIQUEDRUSH | published        |
    And I am logged in as a user with the role "administrator"
    And I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUEDRUSH"
    And I press "edit-submit-search"
    Then I should not see "[MYTEST] DRUSHINDEXARTICLE1 TESTUNIQUEDRUSH"

    When search indexing runs for 1 item
    And I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUEDRUSH"
    And I press "edit-submit-search"
    Then I should see "[MYTEST] DRUSHINDEXARTICLE1 TESTUNIQUEDRUSH"

  @test-trait:Drupal\SearchApiTrait
  Scenario: Assert "When the Search API cron runs" fails when no backend runs a cron hook in the site's process
    Given a configuration listing the backends "drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When the Search API cron runs
      """
    When I run "behat --no-colors"
    Then it should fail with a "DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException" exception:
      """
      No backend provides "DrevOps\BehatSteps\Backend\Drupal\Capability\SearchApiCapabilityInterface". Backends available to this scenario, in order: drush, blackbox.
      """
