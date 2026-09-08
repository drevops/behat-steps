Feature: Check that LanguageTrait works
  As Behat Steps library developer
  I want to provide a step that installs languages
  So that users can write scenarios covering multilingual behaviour

  @api
  Scenario: Assert "Given the following languages exist:" works as expected
    Given the following languages exist:
      | langcode |
      | fr       |
    When I log in as a user with the "administrator" role
    And I visit "/admin/config/regional/language"
    Then I should see "French"

  @trait:Drupal\LanguageTrait
  Scenario: Assert "Given the following languages exist:" fails without a langcode
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given the following languages exist:
        | notlangcode |
        |             |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Each row must carry a non-empty "langcode" value.
      """
