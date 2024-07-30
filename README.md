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

---

## Installation

```bash
composer require --dev drevops/behat-steps:^2
```

## Usage

Add required traits
to your `FeatureContext.php` ([example](tests/behat/bootstrap/FeatureContext.php)):

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

Use `behat -d l` to list all available step definitions.

There are also several pre- and post-scenario hooks that perform data alterations
and cleanup.

| Step Name                                                                                                                    | Description                                                                              | Trait                 | Example                                                                                                                |
|------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------|-----------------------|------------------------------------------------------------------------------------------------------------------------|
| `Given no :type content type`                                                                                                | Delete content type.                                                                     | `ContentTrait`        | [content.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/content.feature)               |
| `When I visit :type :title`                                                                                                  | Navigate to page with specified type and title.                                          | `ContentTrait`        | [content.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/content.feature)               |
| `When I edit :type :title`                                                                                                   | Navigate to edit page with specified type and title.                                     | `ContentTrait`        | [content.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/content.feature)               |
| `When I delete :type :title`                                                                                                 | Navigate to delete page with specified type and title.                                   | `ContentTrait`        | [content.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/content.feature)               |
| `Given no ([a-zA-z0-9_-]+) content:$/`                                                                                       | Remove content defined by provided properties.                                           | `ContentTrait`        | [content.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/content.feature)               |
| `When the moderation state of :type :title changes from :old_state to :new_state`                                            | Change moderation state of a content with specified title.                               | `ContentTrait`        | [content.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/content.feature)               |
| `When I visit :type :title scheduled transitions`                                                                            | Visit scheduled-transition page for node with title.                                     | `ContentTrait`        | [content.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/content.feature)               |
| `Then I save draggable views :view_id view :views_display_id display :bundle items in the following order:`                  | Save order of the Draggable Order items.                                                 | `DraggableViewsTrait` | [draggableviews.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/draggableviews.feature) |
| `Given :bundle :entity_type entities:`                                                                                       | Create eck entities.                                                                     | `EckTrait`            | [eck.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/eck.feature)                       |
| `Given no :bundle :entity_type entities:`                                                                                    | Remove custom entities by field.                                                         | `EckTrait`            | [eck.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/eck.feature)                       |
| `When I edit :bundle :entity_type with title :label`                                                                         | Navigate to edit eck entity page with specified type and title.                          | `EckTrait`            | [eck.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/eck.feature)                       |
| `When I visit :bundle :entity_type with title :label`                                                                        | Navigate to view entity page with specified type and title.                              | `EckTrait`            | [eck.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/eck.feature)                       |
| `Then I( should) see the :selector element with the :attribute attribute set to :value`                                      | Assert that an element with selector and attribute with a value exists.                  | `ElementTrait`        | [element.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/element.feature)               |
| `Given I enable the test email system`                                                                                       | Enable test email system.                                                                | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Given I disable the test email system`                                                                                      | Navigate to edit eck entity page with specified type and title.                          | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `When I clear the test email system queue`                                                                                   | Navigate to view entity page with specified type and title.                              | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then an email is sent to :address`                                                                                          | Assert that an email was sent to an address.                                             | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then no emails were sent`                                                                                                   | Assert that no email messages were sent.                                                 | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then no emails were sent to :address`                                                                                       | Assert that no email messages were sent to a specified address.                          | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then an email header :header contains:`                                                                                     | Assert that an email message header contains specified content.                          | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then an email header :header contains exact:`                                                                               | Assert that an email message header contains exact specified content.                    | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/`                        | Assert that an email message was sent or not sent to a user with content.                | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then an email :field contains`                                                                                              | Assert that an email message field contains a value.                                     | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then an email :field contains exact`                                                                                        | Assert that an email message field contains an exact value.                              | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then an email :field does not contain`                                                                                      | Assert that an email message field does not contain a value.                             | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then an email :field does not contains exact`                                                                               | Assert that an email message field does not contain an exact value.                      | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `When I follow the link number :number in the email with the subject`                                                        | Visit a link from the email.                                                             | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then file :name attached to the email with the subject`                                                                     | Assert that a file is attached to an email message with specified subject.               | `EmailTrait`          | [email.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/email.feature)                   |
| `Then I see field :name`                                                                                                     | Assert that field exists on the page using id,name,label or value.                       | `FieldTrait`          | [field.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/field.feature)                   |
| `Then I don't see field :name`                                                                                               | Assert that field does not exist on the page using id,name,label or value.               | `FieldTrait`          | [field.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/field.feature)                   |
| `Then field :name :exists on the page`                                                                                       | Assert whether the field exists/does not exist on the page using id,name,label or value. | `FieldTrait`          | [field.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/field.feature)                   |
| `Then field :name is :disabled on the page`                                                                                  | Assert whether the field has a state.                                                    | `FieldTrait`          | [field.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/field.feature)                   |
| `Then field :name should be :presence on the page and have state :state`                                                     | Assert whether the field exists on the page and has a state.                             | `FieldTrait`          | [field.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/field.feature)                   |
| `Then I download file from :url`                                                                                             | Download a file from the specified URL.                                                  | `FileDownloadTrait`   | [file-download.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file-download.feature)   |
| `Then I download file from link :link`                                                                                       | Download the file from the specified HTML link.                                          | `FileDownloadTrait`   | [file-download.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file-download.feature)   |
| `Then I see download :link link :presence(on the page)`                                                                      | Assert that an HTML link is present or absent on the page.                               | `FileDownloadTrait`   | [file-download.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file-download.feature)   |
| `Then downloaded file contains:`                                                                                             | Assert the contents of the download file.                                                | `FileDownloadTrait`   | [file-download.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file-download.feature)   |
| `Then downloaded file name is :name`                                                                                         | Assert the file name of the downloaded file.                                             | `FileDownloadTrait`   | [file-download.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file-download.feature)   |
| `Then downloaded file is zip archive that contains files:`                                                                   | Assert downloaded file is a ZIP archive and it contains files.                           | `FileDownloadTrait`   | [file-download.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file-download.feature)   |
| `Then downloaded file is zip archive that does not contain files:`                                                           | Assert downloaded file is a ZIP archive and it does not contain files.                   | `FileDownloadTrait`   | [file-download.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file-download.feature)   |
| `Given managed file:`                                                                                                        | Create managed file with properties provided in the table.                               | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `Given no managed files:`                                                                                                    | Delete managed files defined by provided properties/fields.                              | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `Given unmanaged file :uri created`                                                                                          | Create an unmanaged file.                                                                | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `Given unmanaged file :uri created with content :content`                                                                    | Create an unmanaged file with specified content.                                         | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `Then unmanaged file :uri exists`                                                                                            | Assert that an unmanaged file with specified URI exists.                                 | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `Then unmanaged file :uri does not exist`                                                                                    | Assert that an unmanaged file with specified URI does not exist.                         | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `Then unmanaged file :uri has content :content`                                                                              | Assert that an unmanaged file exists and has specified content.                          | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `Then unmanaged file :uri does not have content :content`                                                                    | Assert that an unmanaged file exists and does not have specified content.                | `FileTrait`           | [file.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/file.feature)                     |
| `When I accept confirmation dialogs`                                                                                         | Accept confirmation dialogs appearing on the page.                                       | `JsTrait`             | [js.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/js.feature)                         |
| `When I do not accept confirmation dialogs`                                                                                  | Do not accept confirmation dialogs appearing on the page.                                | `JsTrait`             | [js.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/js.feature)                         |
| `When /^(?:\|I )click (an?\|on) "(?P<element>[^"]*)" element$/`                                                              | Click on the element defined by the selector.                                            | `JsTrait`             | [js.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/js.feature)                         |
| `When I trigger JS :event event on :selector element`                                                                        | Trigger an event on the specified element.                                               | `JsTrait`             | [js.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/js.feature)                         |
| `Given I press the :keys keys`                                                                                               | Press multiple keyboard keys.                                                            | `KeyboardTrait`       | [keyboard.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/keyboard.feature)             |
| `Given I press the :keys keys on :selector`                                                                                  | Press multiple keyboard keys on element.                                                 | `KeyboardTrait`       | [keyboard.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/keyboard.feature)             |
| `Given I press the :char key`                                                                                                | Press keyboard key.                                                                      | `KeyboardTrait`       | [keyboard.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/keyboard.feature)             |
| `Given I press the :char key on :selector`                                                                                   | TPress keyboard key on element.                                                          | `KeyboardTrait`       | [keyboard.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/keyboard.feature)             |
| `Then I should see the link :text with :href`                                                                                | Assert presence of a link with a href.                                                   | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then I should see the link :text with :href in :locator`                                                                    | Assert presence of a link with a href.                                                   | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then I should not see the link :text with :href`                                                                            | Assert link with a href does not exist.                                                  | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then I should not see the link :text with :href in :locator`                                                                | Assert link with a href does not exist.                                                  | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then the link with title :title exists`                                                                                     | Assert that a link with a title exists.                                                  | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then the link with title :title does not exist`                                                                             | Assert that a link with a title does not exist.                                          | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then I click the link with title :title`                                                                                    | Click on the link with a title.                                                          | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then the link( with title) :text is an absolute link`                                                                       | Assert that the link with a text is absolute.                                            | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Then the link( with title) :text is not an absolute link`                                                                   | Assert that the link with a title is not absolute.                                       | `LinkTrait`           | [link.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/link.feature)                     |
| `Given no :type media type`                                                                                                  | Remove media type.                                                                       | `MediaTrait`          | [media.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/media.feature)                   |
| `Given :type media:`                                                                                                         | Creates media of a given type.                                                           | `MediaTrait`          | [media.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/media.feature)                   |
| `Given /^no ([a-zA-z0-9_-]+) media:$/`                                                                                       | Remove media defined by provided properties.                                             | `MediaTrait`          | [media.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/media.feature)                   |
| `Navigate to edit media with specified type and name.`                                                                       | Assert that the link with a title is not absolute.                                       | `MediaTrait`          | [media.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/media.feature)                   |
| `Given no menus:`                                                                                                            | Remove menu by menu name.                                                                | `MenuTrait`           | [menu.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/menu.feature)                     |
| `Given menus:`                                                                                                               | Create a menu if one does not exist.                                                     | `MenuTrait`           | [menu.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/menu.feature)                     |
| `Given no :menu_name menu_links:`                                                                                            | Remove menu links by title.                                                              | `MenuTrait`           | [menu.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/menu.feature)                     |
| `Given :menu_name menu_links:`                                                                                               | Create menu links.                                                                       | `MenuTrait`           | [menu.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/menu.feature)                     |
| `When :field_name in :bundle :entity_type with :entity_field_name of :entity_field_identifer has :paragraph_type paragraph:` | Creates paragraphs of the given type with fields for existing entity.                    | `ParagraphsTrait`     | [paragraphs.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/paragraphs.feature)         |
| `Then I should be in the :path path`                                                                                         | Assert current page is specified path.                                                   | `PathTrait`           | [path.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/path.feature)                     |
| `Then I should not be in the :path path`                                                                                     | Assert current page is not specified path.                                               | `PathTrait`           | [path.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/path.feature)                     |
| `Then I :can visit :path with HTTP credentials :user :pass`                                                                  | Assert that a path can be visited or not with HTTP credentials.                          | `PathTrait`           | [path.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/path.feature)                     |
| `When I visit :path then the final URL should be :alias`                                                                     | Visit a path and assert the final destination.                                           | `PathTrait`           | [path.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/path.feature)                     |
| `Then response contains header :name`                                                                                        | Assert that a response contains a header with specified name.                            | `ResponseTrait`       | [response.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/response.feature)             |
| `Then response does not contain header :name`                                                                                | Assert that a response does not contain a header with specified name.                    | `ResponseTrait`       | [response.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/response.feature)             |
| `Then response header :name contains :value`                                                                                 | Assert that a response contains a header with specified name and value.                  | `ResponseTrait`       | [response.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/response.feature)             |
| `Then response header :name does not contain :value`                                                                         | Assert a response does not contain a header with specified name and value.               | `ResponseTrait`       | [response.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/response.feature)             |
| `Given role :name with permissions :permissions`                                                                             | Create a single role with specified permissions.                                         | `RoleTrait`           | [role.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/role.feature)                     |
| `Given roles:`                                                                                                               | Create multiple roles from the specified table.                                          | `RoleTrait`           | [role.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/role.feature)                     |
| `Then select :select should have an option :option`                                                                          | Assert that a select has an option.                                                      | `SelectTrait`         | [select.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/select.feature)                 |
| `Then select :select should not have an option :option`                                                                      | Assert that a select does not have an option.                                            | `SelectTrait`         | [select.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/select.feature)                 |
| `Then /^the option "([^"]*)" from select "([^"]*)" is selected$/`                                                            | Assert that a select option is selected.                                                 | `SelectTrait`         | [select.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/select.feature)                 |
| `When I index :type :title for search`                                                                                       | Index a node with all Search API indices.                                                | `SearchApiTrait`      | [search.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/search.feature)                 |
| `When I index :limit Search API items`                                                                                       | Index a number of items across all active Search API indices.                            | `SearchApiTrait`      | [search.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/search.feature)                 |
| `Given vocabulary :vid with name :name exists`                                                                               | Assert that a vocabulary exist.                                                          | `TaxonomyTrait`       | [taxonomy.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/taxonomy.feature)             |
| `Given taxonomy term :name from vocabulary :vocabulary_id exists`                                                            | Assert that a taxonomy term exist by name.                                               | `TaxonomyTrait`       | [taxonomy.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/taxonomy.feature)             |
| `Given no :vocabulary terms:`                                                                                                | Remove terms from a specified vocabulary.                                                | `TaxonomyTrait`       | [taxonomy.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/taxonomy.feature)             |
| `When I visit :vocabulary vocabulary term :name`                                                                             | Visit specified vocabulary term page.                                                    | `TaxonomyTrait`       | [taxonomy.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/taxonomy.feature)             |
| `When I edit :vocabulary vocabulary term :name`                                                                              | Visit specified vocabulary term edit page.                                               | `TaxonomyTrait`       | [taxonomy.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/taxonomy.feature)             |
| `When I visit user :name profile`                                                                                            | Visit profile page of the specified user.                                                | `UserTrait`           | [user.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/user.feature)                     |
| `When I go to my profile edit page`                                                                                          | Visit edit page of the current user.                                                     | `UserTrait`           | [user.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/user.feature)                     |
| `When I edit user :name profile`                                                                                             | Visit edit page of the specified user.                                                   | `UserTrait`           | [user.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/user.feature)                     |
| `Given no users:`                                                                                                            | Remove users specified in the table.                                                     | `UserTrait`           | [user.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/user.feature)                     |
| `Then user :name has :roles role(s) assigned`                                                                                | Assert that a user has roles assigned.                                                   | `UserTrait`           | [user.feature](https://example.com)                                                                                    |
| `Then user :name does not have :roles role(s) assigned`                                                                      | Assert that a user does not have roles assigned.                                         | `UserTrait`           | [user.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/user.feature)                     |
| `Then user :name has :status status`                                                                                         | Assert that a user is active or not.                                                     | `UserTrait`           | [user.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/user.feature)                     |
| `Then I set user :user password to :password`                                                                                | Set a password for a user.                                                               | `UserTrait`           | [user.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/user.feature)                     |
| `Then /^(?:\|I )should see a visible "(?P<selector>[^"]*)" element$/`                                                        | Assert that element with specified CSS is visible on page.                               | `VisibilityTrait`     | [visibility.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/visibility.feature)         |
| `Then /^(?:\|I )should not see a visible "(?P<selector>[^"]*)" element$/`                                                    | Assert that element with specified CSS is visible on page.                               | `VisibilityTrait`     | [visibility.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/visibility.feature)         |
| `Then /^(?:\|I )should see a visually visible "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/`      | Assert that element with specified CSS is visually visible on page.                      | `VisibilityTrait`     | [visibility.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/visibility.feature)         |
| `Then /^(?:\|I )should not see a visually hidden "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/`   | Assert that element with specified CSS is visually hidden on page.                       | `VisibilityTrait`     | [visibility.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/visibility.feature)         |
| `Then /^(?:\|I )wait (\d+) second(s?)$/`                                                                                     | Wait for a specified number of seconds.                                                  | `WaitTrait`           | [wait.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/wait.feature)                     |
| `Given I wait :timeout seconds for AJAX to finish`                                                                           | Wait for AJAX to finish.                                                                 | `WaitTrait`           | [wait.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/wait.feature)                     |
| `When I fill in WYSIWYG :field with :value`                                                                                  | Set value for WYSIWYG field.                                                             | `WysiwygTrait`        | [wysiwyg.feature](https://github.com/drevops/behat-steps/blob/main/tests/behat/features/wysiwyg.feature)               |

#### Skipping before scenario hooks

Some traits provide `beforeScenario` hook implementations. These can be disabled
by adding `behat-steps-skip:METHOD_NAME` tag to your test.

For example, to skip `beforeScenario` hook from `JsTrait`, add
`@behat-steps-skip:jsBeforeScenarioInit` tag to the feature.

## Development

### Local environment setup

- Install [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy), [Ahoy](https://github.com/ahoy-cli/ahoy) and shut down local web services (Apache/Nginx, MAMP etc)
- Checkout project repository in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control).
- `pygmy up`
- `ahoy build`
- Access built site at http://behat-steps.docker.amazee.io/

Use `ahoy --help` to see the list of available commands.

#### Apple Silicon adjustments

`cp docker-compose.override.default.yml docker-compose.override.yml`

### Running tests

The source code of traits is tested by running Behat tests in the same way they would be run in your project: traits are included into [FeatureContext.php](tests/behat/bootstrap/FeatureContext.php) and then ran on the pre-configured [fixture Drupal site](tests/behat/fixtures/d10) using [test features](tests/behat/features).

Run `ahoy build` to setup a fixture Drupal site in the `build` directory.

```bash
ahoy test-bdd                # Run all tests

ahoy test-bdd path/to/file   # Run all scenarios in specific feature file

ahoy test-bdd -- --tags=wip  # Run all scenarios tagged with `@wip` tag
```

#### Debugging tests

- `ahoy debug`
- Set breakpoint
- Run tests with `ahoy test-bdd` - your IDE will pickup an incoming debug connection

#### Updating fixture site

- Build the fixture site and make the required changes
- `ahoy drush cex -y`
- `ahoy update-fixtures` to copy configuration
  changes from build directory to the fixtures directory

---
_Repository created using https://getscaffold.dev/ project scaffold template_
