@d7
Feature: Check that FileTrait works

  @api
  Scenario: Provide files for tests(internal or external)
    When I am logged in as a user with the "administrator" role
    Given managed file:
      | path                                                        |
      | example_image.png                                           |
      | example_document.pdf                                        |
      | example_image.png                                           |
      | https://www.sample-videos.com/img/Sample-jpg-image-50kb.jpg |
