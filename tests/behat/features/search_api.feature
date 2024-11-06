@search
Feature: Ensure Search works.

  @api
  Scenario: Assert "When I add the :content_type content with the title :title to the search index" works as expected
    Given article content:
      | title                                        | moderation_state |
      | [MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT | published        |
      | [MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT     | draft            |
    And I am logged in as a user with the "administrator" role

    # Initial search without indexed nodes.
    When I go to "/search"
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
    Then I should not see the text "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT"

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
