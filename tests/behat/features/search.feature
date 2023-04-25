@d9 @search
Feature: Ensure Search works.

  @api
  Scenario: Assert "I index :type :title for search" works as expected
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
    When I index article "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT" for search
    And I index article "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT" for search

    # Perform another search.
    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTUNIQUETEXT"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    And I press "edit-submit-search"
    Then I should see the text "[MYTEST] TESTPUBLISHEDARTICLE TESTUNIQUETEXT"
    Then I should not see the text "[MYTEST] TESTDRAFTARTICLE TESTUNIQUETEXT"

  @api @testmode
  Scenario: Assert that testmode correctly works with search
    Given article content:
      | title                           | moderation_state |
      | TESTPUBLISHEDARTICLE 1          | published        |
      | [MYTEST] TESTPUBLISHEDARTICLE 2 | published        |
    And I am logged in as a user with the "administrator" role
    And I index article "TESTPUBLISHEDARTICLE 1" for search
    And I index article "[MYTEST] TESTPUBLISHEDARTICLE 2" for search

    When I go to "/search"
    And I fill in "edit-search-api-fulltext" with "TESTPUBLISHEDARTICLE"
    And I press "edit-submit-search"
    Then I should not see the text "TESTPUBLISHEDARTICLE 1"
    And I should see the text "[MYTEST] TESTPUBLISHEDARTICLE 2"
