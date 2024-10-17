Feature: Check that TaxonomyTrait works for or D9

  @api
  Scenario: Assert "Given vocabulary :vid with name :name exists"
    Given no "tags" terms:
      | T1 |
    And "tags" terms:
      | name |
      | T1   |
    And I am logged in as a user with the "administrator" role
    And vocabulary tags with name "Tags" exists
    And taxonomy term "T1" from vocabulary "tags" exists
    When I visit "tags" vocabulary term "T1"
    Then I see the text "T1"

    When I edit "tags" vocabulary term "T1"
    Then I see the text "Edit term"
    And I see the text "T1"

  @trait:TaxonomyTrait
  Scenario: Assert that negative assertion for "Given vocabulary :vid with name :name exists" fails with an error for non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given vocabulary "non_existing_topics" with name "Non Existing Topics" exists
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      "non_existing_topics" vocabulary does not exist
      """

  @trait:TaxonomyTrait
  Scenario: Assert that negative assertion for "Given vocabulary :vid with name :name exists" fails with an error for existing vocabulary with different name
    Given some behat configuration
    And scenario steps:
      """
      Given vocabulary "tags" with name "Tags Fake" exists
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      "tags" vocabulary name is not "Tags Fake"
      """

  @trait:TaxonomyTrait
  Scenario: Assert "Given taxonomy term :name from vocabulary :vocabulary_id exists" fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given taxonomy term "Tag Random 1" from vocabulary "tags" exists
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Taxonomy term "Tag Random 1" from vocabulary "tags" does not exist
      """

  @trait:TaxonomyTrait
  Scenario: Assert "Given taxonomy term :name from vocabulary :vocabulary_id exists" fail with an exception
    Given some behat configuration
    And scenario steps:
      """
      Given taxonomy term "Tag Random 1" from vocabulary "tags_random" exists
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      "tags_random" vocabulary does not exist
      """

  @trait:TaxonomyTrait
  Scenario: Assert "When I visit :vocabulary vocabulary term :name" fail with an exception
    Given some behat configuration
    And scenario steps:
      """
      When I visit "tags" vocabulary term "Random Tag"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find tags term "Random Tag"
      """

  @trait:TaxonomyTrait
  Scenario: Assert "When I edit :vocabulary vocabulary term :name" fail with an exception
    Given some behat configuration
    And scenario steps:
      """
      When I edit "tags" vocabulary term "Random Tag"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find tags term "Random Tag"
      """
