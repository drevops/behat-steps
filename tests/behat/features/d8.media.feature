@d8 @d9
Feature: Check that MediaTrait works for D8

  @api
  Scenario: Assert "When I attach the file :file to :field_name media field"
    Given "image" media:
      | name       | field_media_image |
      | Test media | example_image.png |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/content/media"
    Then I should see the text "Test media"
