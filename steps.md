# Available steps

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


### ContentTrait

[Source](src/ContentTrait.php), [Example](tests/behat/features/content.feature)

#### Delete content type

```gherkin
@Given the content type :content_type does not exist
```
Example:
```gherkin
Given the content type "article" does not exist
```

#### Remove content defined by provided properties

```gherkin
@Given the following :content_type content does not exist:
```
Example:
```gherkin
Given the following "article" content does not exist:
  | title                |
  | Test article         |
  | Another test article |
```

#### Visit a page of a type with a specified title

```gherkin
@When I visit the :content_type content page with the title :title
```
Example:
```gherkin
When I visit the "article" content page with the title "Test article"
```

#### Visit an edit page of a type with a specified title

```gherkin
@When I visit the :content_type content edit page with the title :title
```
Example:
```gherkin
When I visit the "article" content edit page with the title "Test article"
```

#### Visit a delete page of a type with a specified title

```gherkin
@When I visit the :content_type content delete page with the title :title
```
Example:
```gherkin
When I visit the "article" content delete page with the title "Test article"
```

#### Visit a scheduled transitions page of a type with a specified title

```gherkin
@When I visit the :content_type content scheduled transitions page with the title :title
```
Example:
```gherkin
When I visit the "article" content scheduled transitions page with the title "Test article"
```

#### Change moderation state of a content with the specified title

```gherkin
@When I change the moderation state of the :content_type content with the title :title to the :new_state state
```
Example:
```gherkin
When I change the moderation state of the "article" content with the title "Test article" to the "published" state
```

### CookieTrait

[Source](src/CookieTrait.php), [Example](tests/behat/features/cookie.feature)

#### Check if a cookie exists

```gherkin
@Then a cookie with the name :name should exist
```

#### Check if a cookie exists with a specific value

```gherkin
@Then a cookie with the name :name and the value :value should exist
```

#### Check if a cookie exists with a value containing a partial value

```gherkin
@Then a cookie with the name :name and a value containing :partial_value should exist
```

#### Check if a cookie with a partial name exists

```gherkin
@Then a cookie with a name containing :partial_name should exist
```

#### Check if a cookie with a partial name and value exists

```gherkin
@Then a cookie with a name containing :partial_name and the value :value should exist
```

#### Check if a cookie with a partial name and partial value exists

```gherkin
@Then a cookie with a name containing :partial_name and a value containing :partial_value should exist
```

#### Check if a cookie does not exist

```gherkin
@Then a cookie with( the) name :name should not exist
```

#### Check if a cookie with a specific value does not exist

```gherkin
@Then a cookie with the name :name and the value :value should not exist
```

#### Check if a cookie with a value containing a partial value does not exist

```gherkin
@Then a cookie with the name :name and a value containing :partial_value should not exist
```

#### Check if a cookie with a partial name does not exist

```gherkin
@Then a cookie with a name containing :partial_name should not exist
```

#### Check if a cookie with a partial name and value does not exist

```gherkin
@Then a cookie with a name containing :partial_name and the value :value should not exist
```

#### Check if a cookie with a partial name and partial value does not exist

```gherkin
@Then a cookie with a name containing :partial_name and a value containing :partial_value should not exist
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
@Then the field :name should exist
```
Example:
```gherkin
Then the field "Body" should exist
Then the field "field_body" should exist
```

#### Assert that field does not exist on the page using id,name,label or value

```gherkin
@Then the field :name should not exist
```
Example:
```gherkin
Then the field "Body" should not exist
Then the field "field_body" should not exist
```

#### Assert whether the field has a state

```gherkin
@Then the field :name should be :enabled_or_disabled
```
Example:
```gherkin
Then the field "Body" should be "disabled"
Then the field "field_body" should be "disabled"
Then the field "Tags" should be "enabled"
Then the field "field_tags" should be "not enabled"
```

### FileDownloadTrait

[Source](src/FileDownloadTrait.php), [Example](tests/behat/features/file_download.feature)

#### Download a file from the specified URL

```gherkin
@When I download the file from the URL :url
```

#### Download the file from the specified HTML link

```gherkin
@When I download the file from the link :link
```

#### Assert the contents of the download file

```gherkin
@Then the downloaded file should contain:
```

#### Assert the file name of the downloaded file

```gherkin
@Then the downloaded file name should be :name
```

#### Assert the downloaded file name contains a specific string

```gherkin
@Then the downloaded file name should contain :name
```

#### Assert the downloaded file should be a zip archive containing specific files

```gherkin
@Then the downloaded file should be a zip archive containing the files named:
```

#### Assert the downloaded file should be a zip archive containing files with partial names

```gherkin
@Then the downloaded file should be a zip archive containing the files partially named:
```

#### Assert the downloaded file is a zip archive not containing files with partial names

```gherkin
@Then the downloaded file should be a zip archive not containing the files partially named:
```

### FileTrait

[Source](src/FileTrait.php), [Example](tests/behat/features/file.feature)

#### Create managed files with properties provided in the table

```gherkin
@Given the following managed files:
```

#### Delete managed files defined by provided properties/fields

```gherkin
@Given the following managed files do not exist:
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

#### Create an unmanaged file

```gherkin
@Given the unmanaged file at the URI :uri exists
```

#### Create an unmanaged file with specified content

```gherkin
@Given the unmanaged file at the URI :uri exists with :content
```

#### Assert that an unmanaged file with specified URI exists

```gherkin
@Then an unmanaged file at the URI :uri should exist
```

#### Assert that an unmanaged file with specified URI does not exist

```gherkin
@Then an unmanaged file at the URI :uri should not exist
```

#### Assert that an unmanaged file exists and has specified content

```gherkin
@Then an unmanaged file at the URI :uri should contain :content
```

#### Assert that an unmanaged file exists and does not have specified content

```gherkin
@Then an unmanaged file at the URI :uri should not contain :content
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

#### Click on the link with a title

```gherkin
@When I click on the link with the title :title
```
Example:
```gherkin
When I click on the link with the title "Return to site content"
```

#### Assert presence of a link with a href

```gherkin
@Then the link :link with the href :href should exist
```
```gherkin
@Then the link :link with the href :href within the element :locator should exist
```
Example:
```gherkin
Then the link "About us" with the href "/about-us" should exist
Then the link "About us" with the href "/about-us" within the element ".main-nav" should exist
Then the link "About us" with the href "/about*" within the element ".main-nav" should exist
```

#### Assert link with a href does not exist

```gherkin
@Then the link :link with the href :href should not exist
```
```gherkin
@Then the link :link with the href :href within the element :locator should not exist
```
Example:
```gherkin
Then the link "About us" with the href "/about-us" should not exist
Then the link "About us" with the href "/about-us" within the element ".main-nav" should not exist
Then the link "About us" with the href "/about*" within the element ".main-nav" should not exist
```

#### Assert that a link with a title exists

```gherkin
@Then the link with the title :title should exist
```
Example:
```gherkin
Then the link with the title "Return to site content" should exist
```

#### Assert that a link with a title does not exist

```gherkin
@Then the link with the title :title should not exist
```
Example:
```gherkin
Then the link with the title "Some non-existing title" should not exist
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

#### Remove users specified in a table

```gherkin
@Given the following users do not exist:
```
Example:
```gherkin
Given the following users do not exist:
 | name |
 | John |
 | Jane |
 Given the following users do not exist:
  | mail             |
  | john@example.com |
  | jane@example.com |
```

#### Set a password for a user

```gherkin
@Given the password for the user :name is :password
```
Example:
```gherkin
Given the password for the user "John" is "password"
```

#### Set last access time for a user

```gherkin
@Given the last access time for the user :name is :datetime
```
Example:
```gherkin
Given the last access time for the user "John" is "Friday, 22 November 2024 13:46:14"
Given the last access time for the user "John" is "1732319174"
```

#### Set last login time for a user

```gherkin
@Given the last login time for the user :name is :datetime
```
Example:
```gherkin
Given the last login time for the user "John" is "Friday, 22 November 2024 13:46:14"
Given the last login time for the user "John" is "1732319174"
```

#### Visit the profile page of the specified user

```gherkin
@When I visit :name user profile page
```
Example:
```gherkin
When I visit "John" user profile page
```

#### Visit the profile page of the current user

```gherkin
@When I visit my own user profile page
```
Example:
```gherkin
When I visit my own user profile page
```

#### Visit the profile edit page of the specified user

```gherkin
@When I visit :name user profile edit page
```
Example:
```gherkin
When I visit "John" user profile edit page
```

#### Visit the profile edit page of the current user

```gherkin
@When I visit my own user profile edit page
```
Example:
```gherkin
When I visit my own user profile edit page
```

#### Visit the profile delete page of the specified user

```gherkin
@When I visit :name user profile delete page
```
Example:
```gherkin
When I visit "John" user profile delete page
```

#### Visit the profile delete page of the current user

```gherkin
@When I visit my own user profile delete page
```
Example:
```gherkin
When I visit my own user profile delete page
```

#### Assert that a user has roles assigned

```gherkin
@Then the user :name should have the role(s) :roles assigned
```
Example:
```gherkin
Then the user "John" should have the roles "administrator, editor" assigned
```

#### Assert that a user does not have roles assigned

```gherkin
@Then the user :name should not have the role(s) :roles assigned
```
Example:
```gherkin
Then the user "John" should not have the roles "administrator, editor" assigned
```

#### Assert that a user is blocked

```gherkin
@Then the user :name should be blocked
```
Example:
```gherkin
Then the user "John" should be blocked
```

#### Assert that a user is not blocked

```gherkin
@Then the user :name should not be blocked
```
Example:
```gherkin
Then the user "John" should not be blocked
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



[//]: # (END)
