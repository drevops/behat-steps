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
      Unable to find "document" media with the name "Non-existent media".
      """

  @api
  Scenario: Assert that mediaCreate() deletes existing media before creating
    Given the following managed files:
      | path              |
      | example_image.png |

    # Create initial media
    And the following media "image" exist:
      | name                | field_media_image |
      | Duplicate test item | example_image.png |

    And I am logged in as a user with the "administrator" role
    And I visit "/admin/content/media"
    Then I should see the text "Duplicate test item"

    # Create media again with the same name - should replace the first one
    When the following media "image" exist:
      | name                | field_media_image |
      | Duplicate test item | example_image.png |

    And I visit "/admin/content/media"
    Then I should see the text "Duplicate test item"
    # Verify only one media item exists by checking there's exactly one row in the table
    And I should see 1 ".view-media td:contains('Duplicate test item')" elements

  @api
  Scenario: Create single media with vertical field format
    Given I am logged in as a user with the "administrator" role
    And the following managed files:
      | path              |
      | example_image.png |
    And the following image media with fields:
      | name              | [TEST] Vertical Image |
      | field_media_image | example_image.png     |
    When I go to "/admin/content/media"
    Then I should see "[TEST] Vertical Image"

  @api
  Scenario: Create multiple media with vertical field format
    Given I am logged in as a user with the "administrator" role
    And the following managed files:
      | path              |
      | example_image.png |
    And the following image media with fields:
      | name              | [TEST] V-Image 1  | [TEST] V-Image 2  | [TEST] V-Image 3  |
      | field_media_image | example_image.png | example_image.png | example_image.png |
    When I go to "/admin/content/media"
    Then I should see "[TEST] V-Image 1"
    And I should see "[TEST] V-Image 2"
    And I should see "[TEST] V-Image 3"

  @api
  Scenario: Assert that mediaCreateWithFields() deletes existing media before creating
    Given the following managed files:
      | path              |
      | example_image.png |
    And the following image media with fields:
      | name              | [TEST] Duplicate vertical |
      | field_media_image | example_image.png         |
    And I am logged in as a user with the "administrator" role
    And I visit "/admin/content/media"
    Then I should see the text "[TEST] Duplicate vertical"
    When the following image media with fields:
      | name              | [TEST] Duplicate vertical |
      | field_media_image | example_image.png         |
    And I visit "/admin/content/media"
    Then I should see the text "[TEST] Duplicate vertical"
    And I should see 1 ".view-media td:contains('[TEST] Duplicate vertical')" elements
