<p align="center">
  <a href="" rel="noopener"><img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Behat+steps&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Behat steps logo"></a>
</p>

<h1 align="center">A collection of Behat steps for Drupal</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/DrevOps/behat-steps.svg)](https://github.com/DrevOps/behat-steps/pulls)
[![CircleCI](https://circleci.com/gh/drevops/behat-steps.svg?style=shield)](https://circleci.com/gh/drevops/behat-steps)
[![codecov](https://codecov.io/gh/drevops/bats-helpers/graph/badge.svg?token=O0ZYROWCCK)](https://codecov.io/gh/drevops/bats-helpers)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/behat-steps)
![LICENSE](https://img.shields.io/github/license/drevops/behat-steps)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Total Downloads](https://poser.pugx.org/drevops/behat-steps/downloads)](https://packagist.org/packages/drevops/behat-steps)

</div>

> [!IMPORTANT]
> Behat Steps version [3.0](https://github.com/drevops/behat-steps/tree/3.x) is on the way, featuring additional steps and refined, consistent language! Follow the progress in [this issue](https://github.com/drevops/behat-steps/issues/134).

---

## Installation

```bash
composer require --dev drevops/behat-steps:^2
```

## Usage

Add required traits to your `FeatureContext.php` ([example](tests/behat/bootstrap/FeatureContext.php)):

```php
<?php

use Drupal\DrupalExtension\Context\DrupalContext;
use DrevOps\BehatSteps\ContentTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use ContentTrait;

}
```

Modification of `behat.yml` configuration is not required.

### Exceptions

- `\Exception` is thrown for all assertions.
- `\RuntimeException` is thrown for any unfulfilled requirements within a step.

### Available steps

| Step Name                                                                                                                    | Description                                                                                                 |
|------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|
| **[`ContentTrait`](src/ContentTrait.php) ([example](tests/behat/features/content.feature))**                                 |                                                                                                             |
| `Given no :type content type`                                                                                                | Delete the content type.                                                                                    |
| `When I visit :type :title`                                                                                                  | Navigate to a page with a specified type and title.                                                         |
| `When I edit :type :title`                                                                                                   | Navigate to the edit page with a specified type and title.                                                  |
| `When I delete :type :title`                                                                                                 | Navigate to the delete page with a specified type and title.                                                |
| `Given no ([a-zA-z0-9_-]+) content:$/`                                                                                       | Remove content defined by provided properties.                                                              |
| `When the moderation state of :type :title changes from :old_state to :new_state`                                            | Change the moderation state of content with the specified title.                                            |
| `When I visit :type :title scheduled transitions`                                                                            | Visit the scheduled transition page for a node with the specified title.                                    |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`BlockTrait`](src/BlockTrait.php) ([example](tests/behat/features/block.feature))**                                       |                                                                                                             |
| `When I create a block of type :label with:`                                                                                 | Configure an existing block with provided settings (label, region, status, etc.).                            |
| `When I configure the block with the label :label with:`                                                                     | Configure an existing block with provided settings (label, region, status, etc.).                            |
| `When I configure a visibility condition :condition for the block with label :label`                                         | Set a visibility condition for a block.                                                                     |
| `When I remove the visibility condition :condition from the block with label :label`                                         | Remove a visibility condition from a block.                                                                 |
| `When I disable the block with label :label`                                                                                 | Disable a block specified with a label.                                                                     |
| `When I enable the block with label :label`                                                                                  | Enable a block specified with a label.                                                                      |
| `Then block with label :label should exist`                                                                                  | Assert that a block exists.                                                                                 |
| `Then block with label :label should exist in the region :region`                                                            | Assert that a block exists in a specified region.                                                           |
| `Then block with label :label should not exist in the region :region`                                                        | Assert that a block does not exist in a specified region.                                                   |
| `Then the block with label :label should have the visibility condition :condition`                                           | Assert that a block has a visibility condition.                                                             |
| `Then the block with label :label should not have the visibility condition :condition`                                       | Assert that a block does not have a visibility condition.                                                   |
| `Then the block with label :label is disabled`                                                                               | Assert that a block with specified label is disabled.                                                       |
| `Then the block with label :label is enabled`                                                                                | Assert that a block with specified label is enabled.                                                        |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`BlockContentTrait`](src/BlockContentTrait.php) ([example](tests/behat/features/content_block.feature))**                 |                                                                                                             |
| `Given block_content_type :type exists`                                                                                      | Assert that a block content type exists.                                                                    |
| `Given :type block_content:`                                                                                                 | Create block content of a given type with field data.                                                       |
| `Given no :type block_content:`                                                                                              | Remove content blocks from a specified content block type.                                                  |
| `When I edit :type block_content_type with description :description`                                                         | Navigate to specified block content type edit page.                                                         |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`CookieTrait`](src/CookieTrait.php) ([example](tests/behat/features/cookie.feature))**                                    |                                                                                                             |
| `Then a cookie with( the) name :name should exist`                                                                           | Check if a cookie with the specified name exists.                                                           |
| `Then a cookie with( the) name :name and value :value should exist`                                                          | Check if a cookie with the specified name and value exists.                                                 |
| `Then a cookie with( the) name :name and value containing :partial_value should exist`                                       | Check if a cookie with the specified name and a partial value exists.                                       |
| `Then a cookie with( the) name containing :partial_name should exist`                                                        | Check if a cookie with a partial name exists.                                                               |
| `Then a cookie with( the) name containing :partial_name and value :value should exist`                                       | Check if a cookie with a partial name and value exists.                                                     |
| `Then a cookie with( the) name containing :partial_name and value containing :partial_value should exist`                    | Check if a cookie with a partial name and partial value exists.                                             |
| `Then a cookie with( the) name :name should not exist`                                                                       | Check if a cookie with the specified name does not exist.                                                   |
| `Then a cookie with( the) name :name and value :value should not exist`                                                      | Check if a cookie with the specified name and value does not exist.                                         |
| `Then a cookie with( the) name :name and value containing :partial_value should not exist`                                   | Check if a cookie with the specified name and a partial value does not exist.                               |
| `Then a cookie with( the) name containing :partial_name should not exist`                                                    | Check if a cookie with a partial name does not exist.                                                       |
| `Then a cookie with( the) name containing :partial_name and value :value should not exist`                                   | Check if a cookie with a partial name and value does not exist.                                             |
| `Then a cookie with( the) name containing :partial_name and value containing :partial_value should not exist`                | Check if a cookie with a partial name and partial value does not exist.                                     |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`DraggableViewsTrait`](src/DraggableViewsTrait.php) ([example](tests/behat/features/draggableviews.feature))**            |                                                                                                             |
| `Then I save draggable views :view_id view :views_display_id display :bundle items in the following order:`                  | Save the order of the draggable items.                                                                      |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`EckTrait`](src/EckTrait.php) ([example](tests/behat/features/eck.feature))**                                             |                                                                                                             |
| `Given :bundle :entity_type entities:`                                                                                       | Create ECK entities.                                                                                        |
| `Given no :bundle :entity_type entities:`                                                                                    | Remove custom entities by field.                                                                            |
| `When I edit :bundle :entity_type with title :label`                                                                         | Navigate to the edit page for the specified ECK entity type and title.                                      |
| `When I visit :bundle :entity_type with title :label`                                                                        | Navigate to the view page for the specified ECK entity type and title.                                      |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`ElementTrait`](src/ElementTrait.php) ([example](tests/behat/features/element.feature))**                                 |                                                                                                             |
| `Then I( should) see the :selector element with the :attribute attribute set to :value`                                      | Assert that an element with the specified selector and attribute value exists.                              |
| `I( should) see the :selector element with a(n) :attribute attribute containing :value`                                      | Assert that an element with the specified selector and attribute value exists, matching a wildcard pattern. |
| `Then I should see an element :selector using :type contains :text text`                                                     | Assert that an element with selector contains text.                                                         |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`EmailTrait`](src/EmailTrait.php) ([example](tests/behat/features/email.feature))**                                       |                                                                                                             |
| `Given I enable the test email system`                                                                                       | Enable the test email system.                                                                               |
| `Given I disable the test email system`                                                                                      | Disable the test email system.                                                                              |
| `When I clear the test email system queue`                                                                                   | Clear the test email system queue.                                                                          |
| `Then an email is sent to :address`                                                                                          | Assert that an email was sent to the specified address.                                                     |
| `Then no emails were sent`                                                                                                   | Assert that no email messages were sent.                                                                    |
| `Then no emails were sent to :address`                                                                                       | Assert that no email messages were sent to the specified address.                                           |
| `Then an email header :header contains:`                                                                                     | Assert that an email message header contains the specified content.                                         |
| `Then an email header :header contains exact:`                                                                               | Assert that an email message header contains the exact specified content.                                   |
| `Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/`                        | Assert that an email message was sent or not sent to a user with the specified content.                     |
| `Then an email :field contains`                                                                                              | Assert that an email message field contains the specified value.                                            |
| `Then an email :field contains exact`                                                                                        | Assert that an email message field contains the exact specified value.                                      |
| `Then an email :field does not contain`                                                                                      | Assert that an email message field does not contain the specified value.                                    |
| `Then an email :field does not contains exact`                                                                               | Assert that an email message field does not contain the exact specified value.                              |
| `When I follow the link number :number in the email with the subject`                                                        | Visit a link from the email with the specified subject.                                                     |
| `Then file :name attached to the email with the subject`                                                                     | Assert that a file is attached to an email message with the specified subject.                              |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`FieldTrait`](src/FieldTrait.php) ([example](tests/behat/features/field.feature))**                                       |                                                                                                             |
| `Then I see field :name`                                                                                                     | Assert that a field exists on the page using its id, name, label, or value.                                 |
| `Then I don't see field :name`                                                                                               | Assert that a field does not exist on the page using its id, name, label, or value.                         |
| `Then field :name :exists on the page`                                                                                       | Assert whether the field exists on the page using its id, name, label, or value.                            |
| `Then field :name is :disabled on the page`                                                                                  | Assert whether the field is disabled on the page.                                                           |
| `Then field :name should be :presence on the page and have state :state`                                                     | Assert whether the field exists on the page and has a specified state.                                      |
| `Then I fill color in :field for :value`                                                                                     | Fills value for color field.                                                                                |
| `Then color field :field value is :value`                                                                                    | Asserts that a color field has a value.                                                                     |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`FileDownloadTrait`](src/FileDownloadTrait.php) ([example](tests/behat/features/file-download.feature))**                 |                                                                                                             |
| `Then I download file from :url`                                                                                             | Download a file from the specified URL.                                                                     |
| `Then I download file from link :link`                                                                                       | Download a file from the specified HTML link.                                                               |
| `Then I see download :link link :presence(on the page)`                                                                      | Assert that an HTML link is present or absent on the page.                                                  |
| `Then downloaded file contains:`                                                                                             | Assert the contents of the downloaded file.                                                                 |
| `Then downloaded file name is :name`                                                                                         | Assert the file name of the downloaded file.                                                                |
| `Then downloaded file is zip archive that contains files:`                                                                   | Assert that the downloaded file is a ZIP archive containing specified files.                                |
| `Then downloaded file is zip archive that does not contain files:`                                                           | Assert that the downloaded file is a ZIP archive that does not contain specified files.                     |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`FileTrait`](src/FileTrait.php) ([example](tests/behat/features/file.feature))**                                          |                                                                                                             |
| `Given managed file:`                                                                                                        | Create a managed file with the properties provided in the table.                                            |
| `Given no managed files:`                                                                                                    | Delete managed files defined by the provided properties or fields.                                          |
| `Given unmanaged file :uri created`                                                                                          | Create an unmanaged file.                                                                                   |
| `Given unmanaged file :uri created with content :content`                                                                    | Create an unmanaged file with specified content.                                                            |
| `Then unmanaged file :uri exists`                                                                                            | Assert that an unmanaged file with the specified URI exists.                                                |
| `Then unmanaged file :uri does not exist`                                                                                    | Assert that an unmanaged file with the specified URI does not exist.                                        |
| `Then unmanaged file :uri has content :content`                                                                              | Assert that an unmanaged file exists and has the specified content.                                         |
| `Then unmanaged file :uri does not have content :content`                                                                    | Assert that an unmanaged file exists and does not have the specified content.                               |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`JsTrait`](src/JsTrait.php) ([example](tests/behat/features/js.feature))**                                                |                                                                                                             |
| `When I accept confirmation dialogs`                                                                                         | Accept confirmation dialogs appearing on the page.                                                          |
| `When I do not accept confirmation dialogs`                                                                                  | Do not accept confirmation dialogs appearing on the page.                                                   |
| `When /^(?:\|I )click (an?\|on) "(?P<element>[^"]*)" element$/`                                                              | Click on the element defined by the selector.                                                               |
| `When I trigger JS :event event on :selector element`                                                                        | Trigger an event on the specified element.                                                                  |
| `Then /^I scroll to an? element with id "([^"]*)"$/`                                                                         | Scroll to an element with ID.                                                                               |
| `Then the element with id :id should be at the top of the page`                                                              | Assert the element with id at the top of page.                                                              |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`KeyboardTrait`](src/KeyboardTrait.php) ([example](tests/behat/features/keyboard.feature))**                              |                                                                                                             |
| `Given I press the :keys keys`                                                                                               | Press multiple keyboard keys.                                                                               |
| `Given I press the :keys keys on :selector`                                                                                  | Press multiple keyboard keys on the specified element.                                                      |
| `Given I press the :char key`                                                                                                | Press the specified keyboard key.                                                                           |
| `Given I press the :char key on :selector`                                                                                   | Press the specified keyboard key on the specified element.                                                  |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`LinkTrait`](src/LinkTrait.php) ([example](tests/behat/features/link.feature))**                                          |                                                                                                             |
| `Then I should see the link :text with :href`                                                                                | Assert the presence of a link with the specified href.                                                      |
| `Then I should see the link :text with :href in :locator`                                                                    | Assert the presence of a link with the specified href in the specified locator.                             |
| `Then I should not see the link :text with :href`                                                                            | Assert that a link with the specified href does not exist.                                                  |
| `Then I should not see the link :text with :href in :locator`                                                                | Assert that a link with the specified href does not exist in the specified locator.                         |
| `Then the link with title :title exists`                                                                                     | Assert that a link with the specified title exists.                                                         |
| `Then the link with title :title does not exist`                                                                             | Assert that a link with the specified title does not exist.                                                 |
| `Then I click the link with title :title`                                                                                    | Click on the link with the specified title.                                                                 |
| `Then the link( with title) :text is an absolute link`                                                                       | Assert that the link with the specified text is absolute.                                                   |
| `Then the link( with title) :text is not an absolute link`                                                                   | Assert that the link with the specified text is not absolute.                                               |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`MediaTrait`](src/MediaTrait.php) ([example](tests/behat/features/media.feature))**                                       |                                                                                                             |
| `Given no :type media type`                                                                                                  | Remove the specified media type.                                                                            |
| `Given :type media:`                                                                                                         | Create media of the given type.                                                                             |
| `Given /^no ([a-zA-z0-9_-]+) media:$/`                                                                                       | Remove media defined by the provided properties.                                                            |
| `Navigate to edit media with specified type and name.`                                                                       | Navigate to the edit page for the specified media type and name.                                            |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`MenuTrait`](src/MenuTrait.php) ([example](tests/behat/features/menu.feature))**                                          |                                                                                                             |
| `Given no menus:`                                                                                                            | Remove the specified menus.                                                                                 |
| `Given menus:`                                                                                                               | Create a menu if one does not exist.                                                                        |
| `Given no :menu_name menu_links:`                                                                                            | Remove menu links by title.                                                                                 |
| `Given :menu_name menu_links:`                                                                                               | Create menu links.                                                                                          |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`MetaTagTrait`](src/MetaTagTrait.php) ([example](tests/behat/features/metatag.feature))**                                 |                                                                                                             |
| `Then I should see a meta tag with the following attributes:`                                                                | Assert that a meta tag with specific attributes and values exists.                                          |
| `Then I should not see a meta tag with the following attributes:`                                                            | Assert that a meta tag with specific attributes and values does not exist.                                  |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`ParagraphsTrait`](src/ParagraphsTrait.php) ([example](tests/behat/features/paragraphs.feature))**                        |                                                                                                             |
| `When :field_name in :bundle :entity_type with :entity_field_name of :entity_field_identifer has :paragraph_type paragraph:` | Create paragraphs of the given type with fields for the specified entity.                                   |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`PathTrait`](src/PathTrait.php) ([example](tests/behat/features/path.feature))**                                          |                                                                                                             |
| `Then I should be in the :path path`                                                                                         | Assert the current page is the specified path.                                                              |
| `Then I should not be in the :path path`                                                                                     | Assert the current page is not the specified path.                                                          |
| `Then I :can visit :path with HTTP credentials :user :pass`                                                                  | Assert that the specified path can be visited with HTTP credentials.                                        |
| `When I visit :path then the final URL should be :alias`                                                                     | Visit the specified path and assert the final URL.                                                          |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`ResponseTrait`](src/ResponseTrait.php) ([example](tests/behat/features/response.feature))**                              |                                                                                                             |
| `Then response contains header :name`                                                                                        | Assert that the response contains a header with the specified name.                                         |
| `Then response does not contain header :name`                                                                                | Assert that the response does not contain a header with the specified name.                                 |
| `Then response header :name contains :value`                                                                                 | Assert that the response header contains the specified value.                                               |
| `Then response header :name does not contain :value`                                                                         | Assert that the response header does not contain the specified value.                                       |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`RoleTrait`](src/RoleTrait.php) ([example](tests/behat/features/role.feature))**                                          |                                                                                                             |
| `Given role :name with permissions :permissions`                                                                             | Create a single role with the specified permissions.                                                        |
| `Given roles:`                                                                                                               | Create multiple roles from the specified table.                                                             |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`SelectTrait`](src/SelectTrait.php) ([example](tests/behat/features/select.feature))**                                    |                                                                                                             |
| `Then select :select should have an option :option`                                                                          | Assert that the specified select element has the specified option.                                          |
| `Then select :select should not have an option :option`                                                                      | Assert that the specified select element does not have the specified option.                                |
| `Then /^the option "([^"]*)" from select "([^"]*)" is selected$/`                                                            | Assert that the specified option is selected in the specified select element.                               |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`SearchApiTrait`](src/SearchApiTrait.php) ([example](tests/behat/features/search.feature))**                              |                                                                                                             |
| `When I index :type :title for search`                                                                                       | Index a node with all Search API indices.                                                                   |
| `When I index :limit Search API items`                                                                                       | Index a specified number of items across all active Search API indices.                                     |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`TaxonomyTrait`](src/TaxonomyTrait.php) ([example](tests/behat/features/taxonomy.feature))**                              |                                                                                                             |
| `Given vocabulary :vid with name :name exists`                                                                               | Assert that the specified vocabulary exists.                                                                |
| `Given taxonomy term :name from vocabulary :vocabulary_id exists`                                                            | Assert that the specified taxonomy term exists by name.                                                     |
| `Given no :vocabulary terms:`                                                                                                | Remove terms from the specified vocabulary.                                                                 |
| `When I visit :vocabulary vocabulary term :name`                                                                             | Visit the specified vocabulary term page.                                                                   |
| `When I edit :vocabulary vocabulary term :name`                                                                              | Visit the specified vocabulary term edit page.                                                              |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`UserTrait`](src/UserTrait.php) ([example](tests/behat/features/user.feature))**                                          |                                                                                                             |
| `When I visit user :name profile`                                                                                            | Visit the profile page of the specified user.                                                               |
| `When I go to my profile edit page`                                                                                          | Visit the edit page of the current user.                                                                    |
| `When I edit user :name profile`                                                                                             | Visit the edit page of the specified user.                                                                  |
| `Given no users:`                                                                                                            | Remove users specified in the table.                                                                        |
| `Then user :name has :roles role(s) assigned`                                                                                | Assert that a user has the specified roles assigned.                                                        |
| `Then user :name does not have :roles role(s) assigned`                                                                      | Assert that a user does not have the specified roles assigned.                                              |
| `Then user :name has :status status`                                                                                         | Assert whether a user is active or not.                                                                     |
| `Then I set user :user password to :password`                                                                                | Set a password for a user.                                                                                  |
| `Then the last access time of user :name is :time`                                                                           | Set last access time for user.                                                                              |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`VisibilityTrait`](src/VisibilityTrait.php) ([example](tests/behat/features/visibility.feature))**                        |                                                                                                             |
| `Then /^(?:\|I )should see a visible "(?P<selector>[^"]*)" element$/`                                                        | Assert that the element with the specified CSS selector is visible on the page.                             |
| `Then /^(?:\|I )should not see a visible "(?P<selector>[^"]*)" element$/`                                                    | Assert that the element with the specified CSS selector is not visible on the page.                         |
| `Then /^(?:\|I )should see a visually visible "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/`      | Assert that the element with the specified CSS selector is visually visible on the page.                    |
| `Then /^(?:\|I )should not see a visually hidden "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/`   | Assert that the element with the specified CSS selector is visually hidden on the page.                     |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`WaitTrait`](src/WaitTrait.php) ([example](tests/behat/features/wait.feature))**                                          |                                                                                                             |
| `Then /^(?:\|I )wait (\d+) second(s?)$/`                                                                                     | Wait for the specified number of seconds.                                                                   |
| `Given I wait :timeout seconds for AJAX to finish`                                                                           | Wait for AJAX to finish.                                                                                    |
| &nbsp;                                                                                                                       |                                                                                                             |
| **[`WysiwygTrait`](src/WysiwygTrait.php) ([example](tests/behat/features/wysiwyg.feature))**                                 |                                                                                                             |
| `When I fill in WYSIWYG :field with :value`                                                                                  | Set the value for the WYSIWYG field.                                                                        |

#### Skipping before scenario hooks

Some traits provide `beforeScenario` hook implementations. These can be disabled
by adding `behat-steps-skip:METHOD_NAME` tag to your test.

For example, to skip `beforeScenario` hook from `JsTrait`, add
`@behat-steps-skip:jsBeforeScenarioInit` tag to the feature.

## Development

### Local environment setup

- Install [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy), [Ahoy](https://github.com/ahoy-cli/ahoy)
and shut down local web services (Apache/Nginx, MAMP etc)
- Checkout project repository in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control).
- `pygmy up`
- `ahoy build`
- Access built site at http://behat-steps.docker.amazee.io/

Use `ahoy --help` to see the list of available commands.

### Running tests

The source code of traits is tested by running Behat tests in the same way they
would be run in your project: traits are included into [FeatureContext.php](tests/behat/bootstrap/FeatureContext.php)
and then ran on the pre-configured [fixture Drupal site](tests/behat/fixtures/d10)
using [test features](tests/behat/features).

Run `ahoy build` to setup a fixture Drupal site in the `build` directory.

```bash
ahoy test-bdd                # Run all tests

ahoy test-bdd path/to/file   # Run all scenarios in specific feature file

ahoy test-bdd -- --tags=wip  # Run all scenarios tagged with `@wip` tag
```

#### Debugging tests

- `ahoy debug`
- Set breakpoint
- Run tests with `ahoy test-bdd` - your IDE will pickup an incoming debug
  connection

#### Updating fixture site

- Build the fixture site and make the required changes
- `ahoy drush cex -y`
- `ahoy update-fixtures` to copy configuration
  changes from build directory to the fixtures directory

---
_Repository created using https://getscaffold.dev/ project scaffold template_
