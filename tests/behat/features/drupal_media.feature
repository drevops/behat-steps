Feature: Check that MediaTrait works
  As Behat Steps library developer
  I want to provide tools to manage media entities programmatically
  So that users can test media functionality

  @api
  Scenario: Assert "When I attach the file :file to :field_name media field"
    Given the following managed files:
      | path                 |
      | example_document.pdf |

    When the following media "image" do not exist:
      | name             | field_media_image |
      | Test media image | example_image.png |

    And the following media "image" exist:
      | name              | field_media_image |
      | Test media image  | example_image.png |
      | Test media image2 | example_image.png |

    And the following media "image" do not exist:
      | name              |
      | Test media image2 |

    And the following media "document" exist:
      | name                | field_media_document |
      | Test media document | example_document.pdf |

    And I am logged in as a user with the "administrator" role
    And I visit "/admin/content/media"
    Then I should see the text "Test media image"
    And I should not see the text "Test media image2"
    And I should see the text "Test media document"

  @api
  Scenario: Assert navigate to edit media with specified type and name
    Given the following managed files:
      | path                 |
      | example_document.pdf |
    And the following media "document" exist:
      | name                | field_media_document |
      | Test media document | example_document.pdf |
    And I am logged in as a user with the "administrator" role
    When I edit the media "document" with the name "Test media document"
    Then I should see "Edit Document Test media document"

  @api @javascript
  Scenario: Assert remove media type
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/media/add"
    And I fill in "Name" with "test_media_type"
    And I select "image" from "edit-source"
    And I wait for AJAX to finish
    And I select "field_media_image" from "source_configuration[source_field]"
    And I press "Save"
    When I visit "/admin/structure/media"
    Then I should see "test_media_type"
    When "test_media_type" media type does not exist
    And I visit "/admin/structure/media"
    Then I should not see "test_media_type"

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I edit the media :media_type with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I edit the media "document" with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find document media "Non-existent media"
      """
