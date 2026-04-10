@search
Feature: Ensure Search API functionality works
  As Behat Steps library developer
  I want to provide tools to index and search content
  So that users can test search functionality

  @api
  Scenario: Assert "When I add the :content_type content with the title :title to the search index" works as expected
    When I run search indexing for 10 items
    And article content:
      | title                                        | moderation_state |
      | [MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT | published        |
      | [MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT     | draft            |
    And I am logged in as a user with the "administrator" role
    # Initial search without indexed nodes.
    And I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should not see the text "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT"
    And I should not see the text "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT"

    # Index nodes and preform another search.
    When I add the "article" content with the title "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT" to the search index
    And I add the "article" content with the title "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT" to the search index

    # Perform another search.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    Then I should see the text "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT"
    And I should not see the text "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT"

  @api @testmode
  Scenario: Assert "When I add the :content_type content with the title :title to the search index" works as expected with test mode
    Given article content:
      | title                           | moderation_state |
      | TESTPUBLISHEDARTICLE 1          | published        |
      | [MYTEST] TESTPUBLISHEDARTICLE 2 | published        |
    And I am logged in as a user with the "administrator" role
    When I add the "article" content with the title "TESTPUBLISHEDARTICLE 1" to the search index
    And I add the "article" content with the title "[MYTEST] TESTPUBLISHEDARTICLE 2" to the search index

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTPUBLISHEDARTICLE"
    And I press "edit-submit-search"
    Then I should not see the text "TESTPUBLISHEDARTICLE 1"
    And I should see the text "[MYTEST] TESTPUBLISHEDARTICLE 2"

  @api
  Scenario: Assert "When I run search indexing for :count item(s)" works as expected
    Given article content:
      | title                                     | moderation_state |
      | [MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT | published        |
      | [MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT | published        |
      | [MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT | draft            |
    And I am logged in as a user with the "administrator" role

    # Initial search without indexed nodes.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should not see the text "[MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT"
    And I should not see the text "[MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT"
    And I should not see the text "[MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT"

    # Run indexing for a limited number of items (e.g., 1 item).
    When I run search indexing for 1 item

    # Perform another search to verify the indexing.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should see the text "[MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT"
    And I should not see the text "[MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT"
    And I should not see the text "[MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT"

    # Run indexing for more items (e.g., 2 more items, total 3).
    When I run search indexing for 2 items

    # Perform another search to verify all published items are indexed.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should see the text "[MYTEST] INDEXTESTARTICLE1 TESTUNIQUETEXT"
    And I should see the text "[MYTEST] INDEXTESTARTICLE2 TESTUNIQUETEXT"
    And I should not see the text "[MYTEST] INDEXTESTARTICLE3 TESTUNIQUETEXT"

  @api @trait:Drupal\SearchApiTrait
  Scenario: Assert "When I add the :content_type content with the title :title to the search index" fails when content not found
    Given some behat configuration
    And scenario steps:
      """
      When I add the "article" content with the title "Non-existent article" to the search index
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "article" page "Non-existent article".
      """

  @api
  Scenario: Assert "When I run the Search API cron" works as expected
    Given article content:
      | title                                    | moderation_state |
      | [MYTEST] CRONARTICLE1 TESTUNIQUECRONTEXT | published        |
      | [MYTEST] CRONARTICLE2 TESTUNIQUECRONTEXT | published        |
    And I am logged in as a user with the "administrator" role

    # Initial search without indexed nodes.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUECRONTEXT"
    And I press "edit-submit-search"
    Then I should not see the text "[MYTEST] CRONARTICLE1 TESTUNIQUECRONTEXT"
    And I should not see the text "[MYTEST] CRONARTICLE2 TESTUNIQUECRONTEXT"

    # Trigger Search API cron to run the tracker and indexer.
    When I run the Search API cron

    # Perform another search to verify indexing happened via cron.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUECRONTEXT"
    And I press "edit-submit-search"
    Then I should see the text "[MYTEST] CRONARTICLE1 TESTUNIQUECRONTEXT"
    And I should see the text "[MYTEST] CRONARTICLE2 TESTUNIQUECRONTEXT"

  @api
  Scenario: Assert "When I run the Search API Solr cron" is a no-op when Solr module is not enabled
    When I run the Search API Solr cron

  @api @trait:Drupal\SearchApiTrait,Drupal\ModuleTrait
  Scenario: Assert "When I run the Search API cron" fails when search_api module is not enabled
    Given some behat configuration
    And scenario steps tagged with "@api @module:!search_api":
      """
      When I run the Search API cron
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "search_api" module is not enabled.
      """

  @api @trait:Drupal\SearchApiTrait,Drupal\ModuleTrait
  Scenario: Assert "When I run the Search API Solr cron" fails when search_api module is not enabled
    Given some behat configuration
    And scenario steps tagged with "@api @module:!search_api":
      """
      When I run the Search API Solr cron
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "search_api" module is not enabled.
      """
