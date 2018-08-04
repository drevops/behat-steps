@d7
Feature: Check that TaxonomyTrait for D7 works

  Background:
    Given no "tags" terms:
      | T1  |
      | T11 |
      | T12 |
      | T13 |
      | T2  |
    And "tags" terms:
      | name | description         | parent |
      | T1   | parent term         | 0      |
      | T11  | child & parent term | T1     |
      | T12  | child & parent term | T1     |
      | T13  | child & parent term | T1     |
      | T2   | parent term         | 0      |
    And no article content:
      | title           |
      | [TEST] Article1 |
      | [TEST] Article2 |
    And article content:
      | title           | field_tags |
      | [TEST] Article1 | T1, T11    |
      | [TEST] Article2 | T1, T12    |

  @api
  Scenario: Assert "Given taxonomy term :name from vocabulary :vocab exists"
  and "Given :node_title has :field_name field populated with( the following) terms from :vocabulary( vocabulary):"
    Given taxonomy term "T1" from vocabulary "tags" exists
    And "[TEST] Article1" has "field_tags" field populated with the following terms from "tags" vocabulary:
      | T1  |
      | T11 |
    And "[TEST] Article1" has "field_tags" field populated with the following terms from "tags":
      | T1  |
      | T11 |
    And "[TEST] Article1" has "field_tags" field populated with terms from "tags":
      | T1  |
      | T11 |

  @api
  Scenario: Assert "Given :term_name in :vocabulary( vocabulary) has parent :parent_term_name( and depth :depth)"
    Given "T11" in "tags" vocabulary has parent "T1" and depth "1"
    Given "T11" in "tags" vocabulary has parent "T1"
