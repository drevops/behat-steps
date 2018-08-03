@d7
Feature: Check that TaxonomyTrait for D7 works

  Background:
    Given I am logged in as a user with the "administrator" role
    Given "tags" terms:
      | name             | description         | parent           |
      | Test term first  | parent term         | 0                |
      | Test term second | child & parent term | Test term first  |
      | Test term third  | child & parent term | Test term second |
      | Test term fourth | child & parent term | Test term third  |
      | Test term fifth  | child term          | Test term fourth |
    And article content:
      | title              | field_tags                        |
      | Test Article first | Test term first, Test term second |
    And article content:
      | title               | field_tags                       |
      | Test Article second | Test term first, Test term third |

  @api
  Scenario: Assert visiting page with title of specified content type
    And taxonomy term "Test term first" from vocabulary "tags" exists
    Given "Test Article first" node has "field_tags" of "tags" vocabulary with taxonomies:
      | Test term first  |
      | Test term second |

  @api
  Scenario Outline: Assert that taxonomies has a parents
    Given "<taxonomy_child>" has parent "<taxonomy_parent>" with "<level>" level in "tags" vocabulary
    Examples:
      | taxonomy_child   | taxonomy_parent  |
      | Test term first  | 0                |
      | Test term second | Test term first  |
      | Test term third  | Test term second |
      | Test term fourth | Test term third  |
      | Test term fifth  | Test term fourth |
