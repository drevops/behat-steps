Feature: Check that MediaTrait works

  @api
  Scenario: Assert "When I attach the file :file to :field_name media field"
    Given the following managed files:
      | path                 |
      | example_document.pdf |

    And the following media "image" do not exist:
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
    When I visit "/admin/content/media"
    Then I should see the text "Test media image"
    Then I should not see the text "Test media image2"
    And I should see the text "Test media document"

  @api
  Scenario: Assert navigate to edit media with specified type and name
    Given the following managed files:
      | path                 |
      | example_document.pdf |
    And the following media "document" exist:
      | name                | field_media_document |
      | Test media document | example_document.pdf |
    Then I am logged in as a user with the "administrator" role
    When I edit the media "document" with the name "Test media document"
    And I should see "Edit Document Test media document"

  @api @javascript
  Scenario: Assert remove media type
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/media/add"
    Then I fill in "Name" with "test_media_type"
    And I select "image" from "edit-source"
    And I wait for AJAX to finish
    And I select "field_media_image" from "source_configuration[source_field]"
    Then I press "Save"
    Then I visit "/admin/structure/media"
    Then I should see "test_media_type"
    Given "test_media_type" media type does not exist
    Then I visit "/admin/structure/media"
    Then I should not see "test_media_type"
