@d8 @d9 @search
Feature: Ensure Search work.

  Background:
    Given article content:
      | title                                      | moderation_state |
      | [TEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT | published        |
      | [TEST] TESTDRAFTARTICLE TESTUNIQUETEXT     | draft            |

  @api
  Scenario: Assert "I index :type :title for search" works as expected
    # @note Searching as administrator to bypass any Drupal caching.
    Given I am logged in as a user with the "administrator" role

    # Initial search without indexed nodes.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    Then I should not see the text "[TEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT"
    And I should not see the text "[TEST] TESTDRAFTARTICLE TESTUNIQUETEXT"

    # Index nodes and preform another search.
    When I index article "[TEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT" for search
    And I index article "[TEST] TESTDRAFTARTICLE TESTUNIQUETEXT" for search

    # Perform another search.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    Then I should see the text "[TEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT"
    Then I should not see the text "[TEST] TESTDRAFTARTICLE TESTUNIQUETEXT"
