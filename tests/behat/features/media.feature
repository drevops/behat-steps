@d7
Feature: Check that MediaTrait works

  @api
  Scenario: Assert that user can attach file to media field
    When I am logged in as a user with the "administrator" role
    And I go to "node/add/article"
    Then I attach the file "example_image.png" to "Image" media field
