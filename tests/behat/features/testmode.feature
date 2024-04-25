Feature: Ensure TestmodeTrait works.

  Background:
    Given article content:
      | title              |
      | Article 1          |
      | Article 2          |
      | Article 3          |
      | Article 4          |
      | Article 5          |
      | [MYTEST] Article 6 |
      | [MYTEST] Article 7 |

  @api
  Scenario: Assert visiting test content page without test mode will put the required content on the second page
    Given I am logged in as a user with the "administrator" role
    When I go to "/content_test"
    Then I should see the text "Article 1"
    And I should see the text "Article 2"
    And I should see the text "Article 3"
    And I should see the text "Article 4"
    And I should see the text "Article 5"
    And I should not see the text "[MYTEST] Article 6"
    And I should not see the text "[MYTEST] Article 7"

  @api @testmode
  Scenario: Assert visiting test content page without test mode will put the required content on the second page
    Given I am logged in as a user with the "administrator" role
    When I go to "/content_test"
    Then I should not see the text "Article 1"
    And I should not see the text "Article 2"
    And I should not see the text "Article 3"
    And I should not see the text "Article 4"
    And I should not see the text "Article 5"
    And I should see the text "[MYTEST] Article 6"
    And I should see the text "[MYTEST] Article 7"
    And save screenshot
