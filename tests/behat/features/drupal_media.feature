Feature: Check that MediaTrait works
  As Behat Steps library developer
  I want to provide tools to manage media entities programmatically
  So that users can test media functionality

  Scenario: Assert "When I attach the file :file to :field_name media field"
    Given the following managed files exist:
      | path         |
      | document.pdf |

    When the following "image" media do not exist:
      | name             | field_media_image |
      | Test media image | image.png         |

    And the following "image" media exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
      | Test media image2 | image.png         |

    And the following "image" media do not exist:
      | name              |
      | Test media image2 |

    And the following "document" media exist:
      | name                | field_media_document |
      | Test media document | document.pdf         |

    And I log in as a user with the role "administrator"
    And I visit "/admin/content/media"
    Then I should see "Test media image"
    And I should not see "Test media image2"
    And I should see "Test media document"

  Scenario: Assert "When I visit the :media_type media edit page with the name :name" works
    Given the following managed files exist:
      | path         |
      | document.pdf |
    And the following "document" media exist:
      | name                | field_media_document |
      | Test media document | document.pdf         |
    And I log in as a user with the role "administrator"
    When I visit the "document" media edit page with the name "Test media document"
    Then I should see "Edit Document Test media document"

  Scenario: Assert media file field resolves a fixture path in a subdirectory
    Given the following "document" media exist:
      | name                      | field_media_document |
      | Test subdirectory media   | subdir/document.pdf  |
    And I log in as a user with the role "administrator"
    When I visit the "document" media edit page with the name "Test subdirectory media"
    Then I should see "Edit Document Test subdirectory media"
    And the response should contain ".pdf"

  @javascript
  Scenario: Assert remove media type
    When I log in as a user with the role "administrator"
    When I visit "/admin/structure/media/add"
    And I fill in "Name" with "test_media_type"
    And I select "image" from "edit-source"
    And I wait for AJAX to finish
    And I select "field_media_image" from "source_configuration[source_field]"
    And I press "Save"
    When I visit "/admin/structure/media"
    Then I should see "test_media_type"
    When the media type "test_media_type" does not exist
    And I visit "/admin/structure/media"
    Then I should not see "test_media_type"

  @test-trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I visit the :media_type media edit page with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      When I visit the "document" media edit page with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find "document" media with the name "Non-existent media".
      """

  Scenario: Assert that mediaCreateMultiple() deletes existing media before creating
    Given the following managed files exist:
      | path      |
      | image.png |

    And the following "image" media exist:
      | name                | field_media_image |
      | Duplicate test item | image.png         |

    And I log in as a user with the role "administrator"
    And I visit "/admin/content/media"
    Then I should see "Duplicate test item"

    When the following "image" media exist:
      | name                | field_media_image |
      | Duplicate test item | image.png         |

    And I visit "/admin/content/media"
    Then I should see "Duplicate test item"
    And I should see 1 ".view-media td:contains('Duplicate test item')" elements

  @test-trait:Drupal\MediaTrait
  Scenario Outline: Media of a type that is empty or does not exist fails with an exception
    Given some behat configuration
    And scenario steps:
      """
      Given the following "<media_type>" media exist:
        | name         |
        | Orphan media |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      <message>
      """
    Examples:
      | media_type   | message                                                                    |
      |              | Cannot create media because it is missing the required bundle.             |
      | unknown_type | Cannot create media because provided bundle "unknown_type" does not exist. |

  Scenario: Create single media with vertical field format
    When I log in as a user with the role "administrator"
    And the following managed files exist:
      | path      |
      | image.png |
    And the following image media with fields exist:
      | name              | [TEST] Vertical Image |
      | field_media_image | image.png             |
    When I go to "/admin/content/media"
    Then I should see "[TEST] Vertical Image"

  Scenario: Create multiple media with vertical field format
    When I log in as a user with the role "administrator"
    And the following managed files exist:
      | path      |
      | image.png |
    And the following image media with fields exist:
      | name              | [TEST] V-Image 1 | [TEST] V-Image 2 | [TEST] V-Image 3 |
      | field_media_image | image.png        | image.png        | image.png        |
    When I go to "/admin/content/media"
    Then I should see "[TEST] V-Image 1"
    And I should see "[TEST] V-Image 2"
    And I should see "[TEST] V-Image 3"

  Scenario: Assert that mediaCreateMultipleWithFields() deletes existing media before creating
    Given the following managed files exist:
      | path      |
      | image.png |
    And the following image media with fields exist:
      | name              | [TEST] Duplicate vertical |
      | field_media_image | image.png                 |
    And I log in as a user with the role "administrator"
    And I visit "/admin/content/media"
    Then I should see "[TEST] Duplicate vertical"
    When the following image media with fields exist:
      | name              | [TEST] Duplicate vertical |
      | field_media_image | image.png                 |
    And I visit "/admin/content/media"
    Then I should see "[TEST] Duplicate vertical"
    And I should see 1 ".view-media td:contains('[TEST] Duplicate vertical')" elements

  Scenario: Assert "When I visit the :media_type media page with the name :name" works
    Given the following managed files exist:
      | path      |
      | image.png |
    And the following "image" media exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I log in as a user with the role "administrator"
    When I visit the "image" media page with the name "Test media image"
    Then the response should contain "200"

  @test-trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I visit the :media_type media page with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      When I visit the "image" media page with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find "image" media with the name "Non-existent media".
      """

  Scenario: Assert "When I visit the :media_type media delete page with the name :name" works
    Given the following managed files exist:
      | path      |
      | image.png |
    And the following "image" media exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I log in as a user with the role "administrator"
    When I visit the "image" media delete page with the name "Test media image"
    Then the response should contain "200"
    And I should see "Test media image"

  @test-trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I visit the :media_type media delete page with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      When I visit the "image" media delete page with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find "image" media with the name "Non-existent media".
      """

  Scenario: Assert "When I visit the :media_type media revisions page with the name :name" works
    Given the following managed files exist:
      | path      |
      | image.png |
    And the following "image" media exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I log in as a user with the role "administrator"
    When I visit the "image" media revisions page with the name "Test media image"
    Then the response should contain "200"

  @test-trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "When I visit the :media_type media revisions page with the name :name" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      When I visit the "image" media revisions page with the name "Non-existent media"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unable to find "image" media with the name "Non-existent media".
      """

  Scenario: Assert "Then the media type :media_type should exist" works
    When I log in as a user with the role "administrator"
    Then the media type "image" should exist

  @test-trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "Then the media type :media_type should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      Then the media type "nonexistent_type" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The media type "nonexistent_type" does not exist.
      """

  Scenario: Assert "Then the media type :media_type should not exist" works
    When I log in as a user with the role "administrator"
    Then the media type "nonexistent_type" should not exist

  @test-trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "Then the media type :media_type should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      Then the media type "image" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The media type "image" exists, but it should not.
      """

  Scenario: Assert "Then the :media_type media with the name :name should exist" works
    Given the following managed files exist:
      | path      |
      | image.png |
    And the following "image" media exist:
      | name              | field_media_image |
      | Test media image  | image.png         |
    And I log in as a user with the role "administrator"
    Then the "image" media with the name "Test media image" should exist

  @test-trait:Drupal\MediaTrait
  Scenario: Assert that negative assertion for "Then the :media_type media with the name :name should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      Then the "image" media with the name "Non-existent media" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "image" media with the name "Non-existent media" does not exist.
      """

  Scenario: Assert "Then the :media_type media with the name :name should not exist" works
    When I log in as a user with the role "administrator"
    Then the "image" media with the name "Non-existent media" should not exist

  @test-trait:Drupal\MediaTrait,Drupal\FileTrait
  Scenario: Assert that negative assertion for "Then the :media_type media with the name :name should not exist" fails with an error
    Given the following managed files exist:
      | path      |
      | image.png |
    And some behat configuration
    And scenario steps:
      """
      Given the following managed files exist:
        | path      |
        | image.png |
      And the following "image" media exist:
        | name              | field_media_image |
        | Test media image  | image.png         |
      Then the "image" media with the name "Test media image" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "image" media with the name "Test media image" exists, but it should not.
      """
