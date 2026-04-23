Feature: Check that MediaTrait works
  As Behat Steps library developer
  I want to provide tools to manage media entities programmatically
  So that users can test media functionality

  @api
  Scenario: Assert "When I attach the file :file to :field_name media field"
    When the following media "image" do not exist:
      | name             | field_media_image |
      | Test media image | image.png         |

    And the following media "image" exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
      | Test media image2 | image.png         |

    And the following media "image" do not exist:
      | name              |
      | Test media image2 |

    And the following media "document" exist:
      | name                | field_media_document |
      | Test media document | document.pdf         |

    And I am logged in as a user with the "administrator" role
    And I visit "/admin/content/media"
    Then I should see the text "Test media image"
    And I should not see the text "Test media image2"
    And I should see the text "Test media document"

  @api
  Scenario: Assert navigate to edit media with specified type and name
    Given the following media "document" exist:
      | name                | field_media_document |
      | Test media document | document.pdf         |
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
    # Create initial media
    Given the following media "image" exist:
      | name                | field_media_image |
      | Duplicate test item | image.png         |

    And I am logged in as a user with the "administrator" role
    And I visit "/admin/content/media"
    Then I should see the text "Duplicate test item"

    # Create media again with the same name - should replace the first one
    When the following media "image" exist:
      | name                | field_media_image |
      | Duplicate test item | image.png         |

    And I visit "/admin/content/media"
    Then I should see the text "Duplicate test item"
    # Verify only one media item exists by checking there's exactly one row in the table
    And I should see 1 ".view-media td:contains('Duplicate test item')" elements

  @api
  Scenario: Create single media with vertical field format
    Given I am logged in as a user with the "administrator" role
    And the following image media with fields:
      | name              | [TEST] Vertical Image |
      | field_media_image | image.png             |
    When I go to "/admin/content/media"
    Then I should see "[TEST] Vertical Image"

  @api
  Scenario: Create multiple media with vertical field format
    Given I am logged in as a user with the "administrator" role
    And the following image media with fields:
      | name              | [TEST] V-Image 1 | [TEST] V-Image 2 | [TEST] V-Image 3 |
      | field_media_image | image.png        | image.png        | image.png        |
    When I go to "/admin/content/media"
    Then I should see "[TEST] V-Image 1"
    And I should see "[TEST] V-Image 2"
    And I should see "[TEST] V-Image 3"

  @api
  Scenario: Assert that mediaCreateWithFields() deletes existing media before creating
    Given the following image media with fields:
      | name              | [TEST] Duplicate vertical |
      | field_media_image | image.png                 |
    And I am logged in as a user with the "administrator" role
    And I visit "/admin/content/media"
    Then I should see the text "[TEST] Duplicate vertical"
    When the following image media with fields:
      | name              | [TEST] Duplicate vertical |
      | field_media_image | image.png                 |
    And I visit "/admin/content/media"
    Then I should see the text "[TEST] Duplicate vertical"
    And I should see 1 ".view-media td:contains('[TEST] Duplicate vertical')" elements

  @api
  Scenario: Assert "When I visit the media :media_type with the name :name" works
    Given the following media "image" exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I am logged in as a user with the "administrator" role
    When I visit the media "image" with the name "Test media image"
    Then the response should contain "200"

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I visit the media :media_type with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the media "image" with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find "image" media with the name "Non-existent media".
      """

  @api
  Scenario: Assert "When I visit the media :media_type delete page with the name :name" works
    Given the following media "image" exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I am logged in as a user with the "administrator" role
    When I visit the media "image" delete page with the name "Test media image"
    Then the response should contain "200"
    And I should see "Test media image"

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I visit the media :media_type delete page with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the media "image" delete page with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find "image" media with the name "Non-existent media".
      """

  @api
  Scenario: Assert "When I visit the media :media_type revisions page with the name :name" works
    Given the following media "image" exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I am logged in as a user with the "administrator" role
    When I visit the media "image" revisions page with the name "Test media image"
    Then the response should contain "200"

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I visit the media :media_type revisions page with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the media "image" revisions page with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find "image" media with the name "Non-existent media".
      """

  @api
  Scenario: Assert "Then the :media_type media type should exist" works
    Given I am logged in as a user with the "administrator" role
    Then the "image" media type should exist

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "Then the :media_type media type should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the "nonexistent_type" media type should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The media type "nonexistent_type" does not exist.
      """

  @api
  Scenario: Assert "Then the :media_type media type should not exist" works
    Given I am logged in as a user with the "administrator" role
    Then the "nonexistent_type" media type should not exist

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "Then the :media_type media type should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the "image" media type should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The media type "image" exists, but it should not.
      """

  @api
  Scenario: Assert "Then the :media_type media with the name :name should exist" works
    Given the following media "image" exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I am logged in as a user with the "administrator" role
    Then the "image" media with the name "Test media image" should exist

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "Then the :media_type media with the name :name should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the "image" media with the name "Non-existent media" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "image" media with the name "Non-existent media" does not exist.
      """

  @api
  Scenario: Assert "Then the :media_type media with the name :name should not exist" works
    Given I am logged in as a user with the "administrator" role
    Then the "image" media with the name "Non-existent media" should not exist

  @api @trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "Then the :media_type media with the name :name should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given the following media "image" exist:
        | name              | field_media_image |
        | Test media image  | image.png         |
      Then the "image" media with the name "Test media image" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "image" media with the name "Test media image" exists, but it should not.
      """
