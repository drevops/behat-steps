@d7
Feature: Check that FileTrait works

  @api
  Scenario: Assert "Given managed file:"
    When I am logged in as a user with the "administrator" role
    Given managed file:
      | path                                                        |
      | example_document.pdf                                        |
      | example_image.png                                           |
      | example_audio.mp3                                           |
      | https://www.sample-videos.com/img/Sample-jpg-image-50kb.jpg |
    And "example_document.pdf" file object exists
    And "example_image.png" file object exists
    And "example_audio.mp3" file object exists
    And "Sample-jpg-image-50kb.jpg" file object exists
