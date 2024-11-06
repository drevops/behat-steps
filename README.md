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

Add required traits to your
`FeatureContext.php` ([example](tests/behat/bootstrap/FeatureContext.php)):

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

#### Skipping before scenario hooks

Some traits provide `beforeScenario` hook implementations. These can be disabled
by adding `behat-steps-skip:METHOD_NAME` tag to your test.

For example, to skip `beforeScenario` hook from `JsTrait`, add
`@behat-steps-skip:jsBeforeScenarioInit` tag to the feature.

## Available steps

- [ContentTrait](#contenttrait)

- [CookieTrait](#cookietrait)

- [EckTrait](#ecktrait)

- [DraggableviewsTrait](#draggableviewstrait)

- [EmailTrait](#emailtrait)

- [ElementTrait](#elementtrait)

- [FieldTrait](#fieldtrait)

- [FileDownloadTrait](#filedownloadtrait)

- [FileTrait](#filetrait)

- [JsTrait](#jstrait)

- [KeyboardTrait](#keyboardtrait)

- [LinkTrait](#linktrait)

- [MediaTrait](#mediatrait)

- [MenuTrait](#menutrait)

- [ParagraphsTrait](#paragraphstrait)

- [PathTrait](#pathtrait)

- [ResponseTrait](#responsetrait)

- [RoleTrait](#roletrait)

- [SelectTrait](#selecttrait)

- [SearchApiTrait](#searchapitrait)

- [TaxonomyTrait](#taxonomytrait)

- [UserTrait](#usertrait)

- [VisibilityTrait](#visibilitytrait)

- [WaitTrait](#waittrait)

- [WysiwygTrait](#wysiwygtrait)


### ContentTrait

[Source](src/ContentTrait.php), [Example](tests/behat/features/content.feature)

#### Delete content type

```gherkin
@Given no :type content type
```
Example:
```gherkin
Given no "article" content type
```

#### Remove content defined by provided properties

```gherkin
@Given /^no ([a-zA-z0-9_-]+) content:$/
```
Example:
```gherkin
Given no "article" content:
  | title                |
  | Test article         |
  | Another test article |
```

#### Navigate to page with specified type and title

```gherkin
@When I visit :type :title
```
Example:
```gherkin
When I visit "article" "Test article"
```

#### Navigate to edit page with specified type and title

```gherkin
@When I edit :type :title
```
Example:
```gherkin
When I edit "article" "Test article"
```

#### Navigate to delete page with specified type and title

```gherkin
@When I delete :type :title
```

#### Change moderation state of a content with specified title

```gherkin
@When the moderation state of :type :title changes from :old_state to :new_state
```
Example:
```gherkin
When the moderation state of "article" "Test article" changes from "draft" to "published"
```

#### Visit scheduled-transition page for node with title

```gherkin
@When I visit :type :title scheduled transitions
```

### CookieTrait

[Source](src/CookieTrait.php), [Example](tests/behat/features/cookie.feature)

#### Check if a cookie exists

```gherkin
@Then a cookie with( the) name :name should exist
```

#### Check if a cookie exists with a specific value

```gherkin
@Then a cookie with( the) name :name and value :value should exist
```

#### Check if a cookie exists with a value containing a partial value

```gherkin
@Then a cookie with( the) name :name and value containing :partial_value should exist
```

#### Check if a cookie with a partial name exists

```gherkin
@Then a cookie with( the) name containing :partial_name should exist
```

#### Check if a cookie with a partial name and value exists

```gherkin
@Then a cookie with( the) name containing :partial_name and value :value should exist
```

#### Check if a cookie with a partial name and partial value exists

```gherkin
@Then a cookie with( the) name containing :partial_name and value containing :partial_value should exist
```

#### Check if a cookie does not exist

```gherkin
@Then a cookie with( the) name :name should not exist
```

#### Check if a cookie with a specific value does not exist

```gherkin
@Then a cookie with( the) name :name and value :value should not exist
```

#### Check if a cookie with a value containing a partial value does not exist

```gherkin
@Then a cookie with( the) name :name and value containing :partial_value should not exist
```

#### Check if a cookie with a partial name does not exist

```gherkin
@Then a cookie with( the) name containing :partial_name should not exist
```

#### Check if a cookie with a partial name and value does not exist

```gherkin
@Then a cookie with( the) name containing :partial_name and value :value should not exist
```

#### Check if a cookie with a partial name and partial value does not exist

```gherkin
@Then a cookie with( the) name containing :partial_name and value containing :partial_value should not exist
```

### EckTrait

[Source](src/EckTrait.php), [Example](tests/behat/features/eck.feature)

#### Create eck entities

```gherkin
@Given the following eck :bundle :entity_type entities exist:
```
Example:
```gherkin
Given the following eck "contact" "contact_type" entities exist:
| title  | field_marine_animal     | field_fish_type | ... |
| Snook  | Fish                    | Marine fish     | 10  |
| ...    | ...                     | ...             | ... |
```

#### Remove custom entities by field

```gherkin
@Given the following eck :bundle :entity_type entities do not exist:
```
Example:
```gherkin
Given the following eck "contact" "contact_type" entities do not exist:
| field        | value           |
| field_a      | Entity label    |
```

#### Navigate to view entity page with specified type and title

```gherkin
@When I visit eck :bundle :entity_type entity with the title :title
```
Example:
```gherkin
When I visit eck "contact" "contact_type" entity with the title "Test contact"
```

#### Navigate to edit eck entity page with specified type and title

```gherkin
@When I edit eck "contact" "contact_type" entity with the title "Test contact"
```
```gherkin
@When I edit eck :bundle :entity_type entity with the title :title
```

### DraggableviewsTrait

[Source](src/DraggableviewsTrait.php), [Example](tests/behat/features/draggableviews.feature)

#### Save order of the Draggable Order items

```gherkin
@When I save the draggable views items of the view :view_id and the display :views_display_id for the :bundle content in the following order:
```

### EmailTrait

[Source](src/EmailTrait.php), [Example](tests/behat/features/email.feature)

#### Clear test email system queue

```gherkin
@When I clear the test email system queue
```

#### Visit a link from the email

```gherkin
@When I follow the link number :number in the email with the subject
```
```gherkin
@When I follow the link number :number in the email with the subject:
```

#### Assert that an email was sent to an address

```gherkin
@Then an email is sent to :address
```

#### Assert that no email messages were sent

```gherkin
@Then no emails were sent
```

#### Assert that no email messages were sent to a specified address

```gherkin
@Then no emails were sent to :address
```

#### Assert that an email message header contains specified content

```gherkin
@Then an email header :header contains:
```

#### Assert that an email message header contains exact specified content

```gherkin
@Then an email header :header contains exact:
```

#### Assert that an email message was sent or not sent to a user with content

```gherkin
@Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/
```

#### Assert that an email message field contains a value

```gherkin
@Then an email :field contains
```
```gherkin
@Then an email :field contains:
```

#### Assert that an email message field contains an exact value

```gherkin
@Then an email :field contains exact
```
```gherkin
@Then an email :field contains exact:
```

#### Assert that an email message field does not contain a value

```gherkin
@Then an email :field does not contain
```
```gherkin
@Then an email :field does not contain:
```

#### Assert that an email message field does not contain an exact value

```gherkin
@Then an email :field does not contain exact
```
```gherkin
@Then an email :field does not contain exact:
```

#### Assert that a file is attached to an email message with specified subject

```gherkin
@Then file :name attached to the email with the subject
```
```gherkin
@Then file :name attached to the email with the subject:
```

### ElementTrait

[Source](src/ElementTrait.php), [Example](tests/behat/features/element.feature)

#### Assert an element with selector and attribute with a value exists

```gherkin
@Then the element :selector with the attribute :attribute and the value :value should exist
```

#### Assert an element with selector and attribute containing a value exists

```gherkin
@Then the element :selector with the attribute :attribute and the value containing :value should exist
```

#### Assert an element with selector and attribute with a value exists

```gherkin
@Then the element :selector with the attribute :attribute and the value :value should not exist
```

#### Assert an element with selector and attribute containing a value does not exist

```gherkin
@Then the element :selector with the attribute :attribute and the value containing :value should not exist
```

### FieldTrait

[Source](src/FieldTrait.php), [Example](tests/behat/features/field.feature)

#### Assert that field exists on the page using id,name,label or value

```gherkin
@Then I see field :name
```
Example:
```gherkin
Then I see field "Body"
Then I see field "field_body"
```

#### Assert that field does not exist on the page using id,name,label or value

```gherkin
@Then I don't see field :name
```
Example:
```gherkin
Then I don't see field "Body"
Then I don't see field "field_body"
```

#### Assert whether the field exists on the page using id,name,label or value

```gherkin
@Then field :name :exists on the page
```
Example:
```gherkin
Then field "Body" "exists" on the page
Then field "field_body" "exists" on the page
Then field "Tags" "does not exist" on the page
Then field "field_tags" "does not exist" on the page
```

#### Assert whether the field has a state

```gherkin
@Then field :name is :disabled on the page
```
Example:
```gherkin
Then field "Body" is "disabled" on the page
Then field "field_body" is "disabled" on the page
Then field "Tags" is "enabled" on the page
Then field "field_tags" is "not enabled" on the page
```

#### Assert whether the field exists on the page and has a state

```gherkin
@Then field :name should be :presence on the page and have state :state
```
Example:
```gherkin
Then field "Body" should be "present" on the page and have state "enabled"
Then field "Tags" should be "absent" on the page and have state "n/a"
```

### FileDownloadTrait

[Source](src/FileDownloadTrait.php), [Example](tests/behat/features/file_download.feature)

#### Download a file from the specified URL

```gherkin
@Then I download file from :url
```

#### Download the file from the specified HTML link

```gherkin
@Then I download file from link :link
```

#### Assert that an HTML link is present or absent on the page

```gherkin
@Then I see download :link link :presence(on the page)
```

#### Assert the contents of the download file

```gherkin
@Then downloaded file contains:
```

#### Assert the file name of the downloaded file

```gherkin
@Then downloaded file name is :name
```

#### Assert downloaded file is a ZIP archive and it contains files

```gherkin
@Then downloaded file is zip archive that contains files:
```

#### Assert downloaded file is a ZIP archive and it does not contain files

```gherkin
@Then downloaded file is zip archive that does not contain files:
```

### FileTrait

[Source](src/FileTrait.php), [Example](tests/behat/features/file.feature)

#### Create managed file with properties provided in the table

```gherkin
@Given managed file:
```

#### Delete managed files defined by provided properties/fields

```gherkin
@Given no managed files:
```
Example:
```gherkin
Given no managed files:
| filename      |
| myfile.jpg    |
| otherfile.jpg |
 Given no managed files:
 | uri                    |
 | public://myfile.jpg    |
 | public://otherfile.jpg |
```

#### Create an unmanaged file with specified content

```gherkin
@Given unmanaged file :uri created
```

#### Create an unmanaged file with specified content

```gherkin
@Given unmanaged file :uri created with content :content
```

#### Assert that an unmanaged file with specified URI exists

```gherkin
@Then unmanaged file :uri exists
```

#### Assert that an unmanaged file with specified URI does not exist

```gherkin
@Then unmanaged file :uri does not exist
```

#### Assert that an unmanaged file exists and has specified content

```gherkin
@Then unmanaged file :uri has content :content
```

#### Assert that an unmanaged file exists and does not have specified content

```gherkin
@Then unmanaged file :uri does not have content :content
```

### JsTrait

[Source](src/JsTrait.php), [Example](tests/behat/features/js.feature)

#### Accept confirmation dialogs appearing on the page

```gherkin
@When I accept confirmation dialogs
```
Example:
```gherkin
When I accept confirmation dialogs
```

#### Do not accept confirmation dialogs appearing on the page

```gherkin
@When I do not accept confirmation dialogs
```
Example:
```gherkin
When I do not accept confirmation dialogs
```

#### Click on the element defined by the selector

```gherkin
@When /^(?:|I )click (an?|on) "(?P<element>[^"]*)" element$/
```
Example:
```gherkin
When I click on ".button" element
When I click ".button" element
When click ".button" element
```

#### Trigger an event on the specified element

```gherkin
@When I trigger JS :event event on :selector element
```

### KeyboardTrait

[Source](src/KeyboardTrait.php), [Example](tests/behat/features/keyboard.feature)

#### Press multiple keyboard keys, optionally on element

```gherkin
@When I press the keys :keys
```
```gherkin
@When I press the keys :keys on the element :selector
```

#### Press keyboard key, optionally on element

```gherkin
@When I press the key :char
```
```gherkin
@When I press the key :char on the element :selector
```

### LinkTrait

[Source](src/LinkTrait.php), [Example](tests/behat/features/link.feature)

#### Assert presence of a link with a href

```gherkin
@Then I should see the link :text with :href
```
```gherkin
@Then I should see the link :text with :href in :locator
```
Example:
```gherkin
Then I should see the link "About us" with "/about-us"
Then I should see the link "About us" with "/about-us" in ".main-nav"
Then I should see the link "About us" with "/about*" in ".main-nav"
```

#### Assert link with a href does not exist

```gherkin
@Then I should not see the link :text with :href
```
```gherkin
@Then I should not see the link :text with :href in :locator
```
Example:
```gherkin
Then I should not see the link "About us" with "/about-us"
Then I should not see the link "About us" with "/about-us" in ".main-nav"
Then I should not see the link "About us" with "/about*" in ".main-nav"
```

#### Assert that a link with a title exists

```gherkin
@Then the link with title :title exists
```

#### Assert that a link with a title does not exist

```gherkin
@Then the link with title :title does not exist
```

#### Click on the link with a title

```gherkin
@Then I click the link with title :title
```

### MediaTrait

[Source](src/MediaTrait.php), [Example](tests/behat/features/media.feature)

#### Remove media type

```gherkin
@Given no "video" media type
```
```gherkin
@Given no :type media type
```

#### Creates media of a given type

```gherkin
@Given :type media:
```
Example:
```gherkin
Given "video" media:
| name     | field1   | field2 | field3           |
| My media | file.jpg | value  | value            |
| ...      | ...      | ...    | ...              |
```

#### Remove media defined by provided properties

```gherkin
@Given /^no ([a-zA-z0-9_-]+) media:$/
```
Example:
```gherkin
Given no "image" media:
| name               |
| Media item         |
| Another media item |
```

#### Navigate to edit media with specified type and name

```gherkin
@When I edit :type media :name
```
Example:
```gherkin
When I edit "document" media "Test document"
```

### MenuTrait

[Source](src/MenuTrait.php), [Example](tests/behat/features/menu.feature)

#### Remove menu by menu name

```gherkin
@Given no menus:
```

#### Create a menu if one does not exist

```gherkin
@Given menus:
```

#### Remove menu links by title

```gherkin
@Given no :menu_name menu_links:
```

#### Create menu links

```gherkin
@Given :menu_name menu_links:
```

### ParagraphsTrait

[Source](src/ParagraphsTrait.php), [Example](tests/behat/features/paragraphs.feature)

#### Create a paragraph of the given type with fields within an existing entity

```gherkin
@Given the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:
```
Example:
```gherkin
Given the following fields for the paragraph "text" exist in the field "field_component" within the "landing_page" "node" identified by the field "title" and the value "My landing page":
| field_paragraph_title           | My paragraph title   |
| field_paragraph_longtext:value  | My paragraph message |
| field_paragraph_longtext:format | full_html            |
| ...                             | ...                  |
```

### PathTrait

[Source](src/PathTrait.php), [Example](tests/behat/features/path.feature)

#### Set basic authentication for the current session

```gherkin
@Given the basic authentication with the username :username and the password :password
```
Example:
```gherkin
Given the basic authentication with the username "myusername" and the password "mypassword"
```

#### Assert that the current page is a specified path

```gherkin
@Then the path should be :path
```
Example:
```gherkin
Then the path should be "/about-us"
Then the path should be "<front>"
```

#### Assert that the current page is not a specified path

```gherkin
@Then the path should not be :path
```
Example:
```gherkin
Then the path should not be "/about-us"
Then the path should not be "<front>"
```

### ResponseTrait

[Source](src/ResponseTrait.php), [Example](tests/behat/features/response.feature)

#### Assert that a response contains a header with specified name

```gherkin
@Then the response should contain the header :header_name
```
Example:
```gherkin
Then the response should contain the header "Connection"
```

#### Assert that a response does not contain a header with a specified name

```gherkin
@Then the response should not contain the header :header_name
```
Example:
```gherkin
Then the response should not contain the header "Connection"
```

#### Assert that a response contains a header with a specified name and value

```gherkin
@Then the response header :header_name should contain the value :header_value
```
Example:
```gherkin
Then the response header "Connection" should contain the value "Keep-Alive"
```

#### Assert a response does not contain a header with a specified name and value

```gherkin
@Then the response header :header_name should not contain the value :header_value
```
Example:
```gherkin
Then the response header "Connection" should not contain the value "Keep-Alive"
```

### RoleTrait

[Source](src/RoleTrait.php), [Example](tests/behat/features/role.feature)

#### Create a single role with specified permissions

```gherkin
@Given the role :role_name with the permissions :permissions
```

#### Create multiple roles from the specified table

```gherkin
@Given the following roles:
```

### SelectTrait

[Source](src/SelectTrait.php), [Example](tests/behat/features/select.feature)

#### Assert that a select has an option

```gherkin
@Then select :select should have an option :option
```

#### Assert that a select does not have an option

```gherkin
@Then select :select should not have an option :option
```

#### Assert that a select option is selected

```gherkin
@Then /^the option "([^"]*)" from select "([^"]*)" is selected$/
```

### SearchApiTrait

[Source](src/SearchApiTrait.php), [Example](tests/behat/features/search_api.feature)

#### Index a node of a specific content type with a specific title

```gherkin
@When I add the :content_type content with the title :title to the search index
```

#### Run indexing for a specific number of items

```gherkin
@When I run search indexing for :count item(s)
```

### TaxonomyTrait

[Source](src/TaxonomyTrait.php), [Example](tests/behat/features/taxonomy.feature)

#### Remove terms from a specified vocabulary

```gherkin
@Given the following :vocabulary_machine_name vocabulary terms do not exist:
```
Example:
```gherkin
Given the following "fruits" vocabulary terms do not exist:
  | Apple |
  | Pear  |
```

#### Visit specified vocabulary term page

```gherkin
@When I visit the :vocabulary_machine_name vocabulary :term_name term page
```
Example:
```gherkin
When I visit the "fruits" vocabulary "Apple" term page
```

#### Edit specified vocabulary term page

```gherkin
@When I edit the :vocabulary_machine_name vocabulary :term_name term page
```
Example:
```gherkin
When I edit the "fruits" vocabulary "Apple" term page
```

#### Assert that a vocabulary with a specific name exists

```gherkin
@Then the vocabulary :machine_name with the name :name should exist
```
Example:
```gherkin
Then the vocabulary "topics" with the name "Topics" should exist
```

#### Assert that a vocabulary with a specific name does not exist

```gherkin
@Then the vocabulary :machine_name should not exist
```
Example:
```gherkin
Then the vocabulary "topics" should not exist
```

#### Assert that a taxonomy term exist by name

```gherkin
@Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist
```
Example:
```gherkin
Then the taxonomy term "Apple" from the vocabulary "Fruits" should exist
```

#### Assert that a taxonomy term does not exist by name

```gherkin
@Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist
```
Example:
```gherkin
Then the taxonomy term "Apple" from the vocabulary "Fruits" should not exist
```

### UserTrait

[Source](src/UserTrait.php), [Example](tests/behat/features/user.feature)

#### Remove users specified in the table

```gherkin
@Given no users:
```

#### Visit profile page of the specified user

```gherkin
@When I visit user :name profile
```

#### Visit edit page of the current user

```gherkin
@When I go to my profile edit page
```

#### Visit edit page of the specified user

```gherkin
@When I edit user :name profile
```

#### Assert that a user has roles assigned

```gherkin
@Then user :name has :roles role(s) assigned
```

#### Assert that a user does not have roles assigned

```gherkin
@Then user :name does not have :roles role(s) assigned
```

#### Assert that a user is active or not

```gherkin
@Then user :name has :status status
```

#### Set a password for a user

```gherkin
@Then I set user :user password to :password
```

### VisibilityTrait

[Source](src/VisibilityTrait.php), [Example](tests/behat/features/visibility.feature)

#### Assert that element with specified CSS is visible on page

```gherkin
@Then /^(?:|I )should see a visible "(?P<selector>[^"]*)" element$/
```

#### Assert that element with specified CSS is visible on page

```gherkin
@Then /^(?:|I )should not see a visible "(?P<selector>[^"]*)" element$/
```

#### Assert that element with specified CSS is visually visible on page

```gherkin
@Then /^(?:|I )should see a visually visible "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/
```

#### Assert that element with specified CSS is visually hidden on page

```gherkin
@Then /^(?:|I )should not see a visually hidden "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/
```

### WaitTrait

[Source](src/WaitTrait.php), [Example](tests/behat/features/wait.feature)

#### Wait for a specified number of seconds

```gherkin
@When I wait for :seconds second(s)
```

#### Wait for the AJAX calls to finish

```gherkin
@When I wait for :seconds second(s) for AJAX to finish
```

### WysiwygTrait

[Source](src/WysiwygTrait.php), [Example](tests/behat/features/wysiwyg.feature)

#### Set value for WYSIWYG field

```gherkin
@When /^(?:|I )fill in WYSIWYG "(?P<field>(?:[^"]|\")*)" with "(?P<value>(?:[^"]|\")*)"$/
```



## Development

### Local environment setup

Install [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy), [Ahoy](https://github.com/ahoy-cli/ahoy)
and shut down local web services (Apache/Nginx, MAMP etc)

- Checkout project repository in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control).
- `pygmy up`
- `ahoy build`
- Access built site at http://behat-steps.docker.amazee.io/

Use `ahoy --help` to see the list of available commands.

### Running tests

The source code of traits is tested by running Behat tests in the same way they
would be run in your project: traits are included
into [FeatureContext.php](tests/behat/bootstrap/FeatureContext.php)
and then ran on the
pre-configured [fixture Drupal site](tests/behat/fixtures/d10)
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

#### Updating documentation

```
php docs.php
```

---
_Repository created using https://getscaffold.dev/ project scaffold template_
