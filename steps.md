# Available steps

- [BlockTrait](#blocktrait)

- [BlockContentTrait](#blockcontenttrait)

- [CookieTrait](#cookietrait)

- [ContentTrait](#contenttrait)

- [EckTrait](#ecktrait)

- [DraggableviewsTrait](#draggableviewstrait)

- [EmailTrait](#emailtrait)

- [ElementTrait](#elementtrait)

- [FieldTrait](#fieldtrait)

- [FileDownloadTrait](#filedownloadtrait)

- [FileTrait](#filetrait)

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


### BlockTrait

[Source](src/BlockTrait.php), [Example](tests/behat/features/block.feature)

#### Creates, configures and places a block in the default theme region

```gherkin
@When I create a block of type :label with:
```
Example:

```gherkin
| label         | [TEST] Welcome Message      |
| label_display | 1                           |
| region        | sidebar_first               |
| status        | 1                           |
```

#### Finds and configures an existing block identified by its label

```gherkin
@When I configure the block with the label :label with:
```
Example:

```gherkin
 | label         | [TEST] Updated Message      |
 | label_display | 1                           |
 | region        | sidebar_second              |
 | status        | 1                           |
```

#### Sets a visibility condition for a block

```gherkin
@When I configure a visibility condition :condition for the block with label :label
```
Example:

```gherkin
  When I configure a visibility condition "request_path" for the block with label "[TEST] Block"
  | pages | /node/1\r\n/about |
  | negate | 0 |
```

#### Removes a visibility condition from the specified block

```gherkin
@When I remove the visibility condition :condition from the block with label :label
```
Example:

```gherkin
  When I remove the visibility condition "request_path" from the block with label "[TEST] Block"
```

#### Disables a block specified by its label

```gherkin
@When I disable the block with label :label
```
Example:

```gherkin
  When I disable the block with label "[TEST] Sidebar Block"
```

#### Enables a block specified by its label

```gherkin
@When I enable the block with label :label
```
Example:

```gherkin
  When I enable the block with label "[TEST] Sidebar Block"
```

#### Verifies that a block with the specified label exists in a specific region

```gherkin
@When block with label :label should exist in the region :region
```
Example:

```gherkin
  Then block with label "[TEST] User Menu" should exist in the region "sidebar_first"
```

#### Verifies that a block does not exist in a specific region

```gherkin
@When block with label :label should not exist in the region :region
```
Example:

```gherkin
  Then block with label "[TEST] User Menu" should not exist in the region "content"
```

#### Verifies that a block with the specified label exists in the default theme

```gherkin
@Then block with label :label should exist
```
Example:

```gherkin
  Then block with label "[TEST] Footer Block" should exist
```

#### Verifies that a block has a specific visibility condition configured

```gherkin
@Then the block with label :label should have the visibility condition :condition
```
Example:

```gherkin
  Then the block with label "[TEST] Admin Block" should have the visibility condition "user_role"
```

#### Asserts that a block does not have a specific visibility condition

```gherkin
@Then the block with label :label should not have the visibility condition :condition
```
Example:

```gherkin
  Then the block with label "[TEST] Public Block" should not have the visibility condition "user_role"
```

#### Verifies that a block with the specified label is disabled (inactive)

```gherkin
@Then the block with label :label is disabled
```
Example:

```gherkin
  Then the block with label "[TEST] Maintenance Block" is disabled
```

#### Verifies that a block with the specified label is enabled

```gherkin
@Then the block with label :label is enabled
```
Example:

```gherkin
  Then the block with label "[TEST] Navigation Block" is enabled
```

### BlockContentTrait

[Source](src/BlockContentTrait.php), [Example](tests/behat/features/block_content.feature)

#### Verifies that a custom block type exists

```gherkin
@Given the custom block type ":type" exists
```
Example:

```gherkin
Given the custom block type "Search" exists
```

#### Removes custom blocks of a specified type with the given descriptions

```gherkin
@Given the following ":type" custom blocks do not exist:
```
Example:

```gherkin
Given the following "basic" custom blocks do not exist:
| [TEST] Footer Block  |
| [TEST] Contact Form  |
```

#### Creates custom blocks of the specified type with the given field values

```gherkin
@Given the following ":type" custom blocks exist:
```
Example:

```gherkin
  Given the following "basic" custom blocks exist:
  | info                  | status | body                   | created           |
  | [TEST] Footer Contact | 1      | Call us at 555-1234    | 2023-01-17 8:00am |
  | [TEST] Copyright      | 1      | © 2023 Example Company | 2023-01-18 9:00am |
```

#### Navigates to the edit page for a specified custom block

```gherkin
@When I edit the ":type" custom block with description ":description"
```
Example:

```gherkin
When I edit the "basic" custom block with description "[TEST] Footer Block"
```

### CookieTrait

[Source](src/CookieTrait.php), [Example](tests/behat/features/cookie.feature)

#### Check if a cookie exists

```gherkin
@Then a cookie with the name :name should exist
```
Example:

```gherkin
Then a cookie with the name "session_id" should exist
```

#### Check if a cookie exists with a specific value

```gherkin
@Then a cookie with the name :name and the value :value should exist
```
Example:

```gherkin
Then a cookie with the name "language" and the value "en" should exist
```

#### Check if a cookie exists with a value containing a partial value

```gherkin
@Then a cookie with the name :name and a value containing :partial_value should exist
```
Example:

```gherkin
Then a cookie with the name "preferences" and a value containing "darkmode" should exist
```

#### Check if a cookie with a partial name exists

```gherkin
@Then a cookie with a name containing :partial_name should exist
```
Example:

```gherkin
Then a cookie with a name containing "session" should exist
```

#### Check if a cookie with a partial name and value exists

```gherkin
@Then a cookie with a name containing :partial_name and the value :value should exist
```
Example:

```gherkin
Then a cookie with a name containing "user" and the value "admin" should exist
```

#### Check if a cookie with a partial name and partial value exists

```gherkin
@Then a cookie with a name containing :partial_name and a value containing :partial_value should exist
```
Example:

```gherkin
Then a cookie with a name containing "user" and a value containing "admin" should exist
```

#### Check if a cookie does not exist

```gherkin
@Then a cookie with the name :name should not exist
```
Example:

```gherkin
Then a cookie with name "old_session" should not exist
```

#### Check if a cookie with a specific value does not exist

```gherkin
@Then a cookie with the name :name and the value :value should not exist
```
Example:

```gherkin
Then a cookie with the name "language" and the value "fr" should not exist
```

#### Check if a cookie with a value containing a partial value does not exist

```gherkin
@Then a cookie with the name :name and a value containing :partial_value should not exist
```
Example:

```gherkin
Then a cookie with the name "preferences" and a value containing "lightmode" should not exist
```

#### Check if a cookie with a partial name does not exist

```gherkin
@Then a cookie with a name containing :partial_name should not exist
```
Example:

```gherkin
Then a cookie with a name containing "old" should not exist
```

#### Check if a cookie with a partial name and value does not exist

```gherkin
@Then a cookie with a name containing :partial_name and the value :value should not exist
```
Example:

```gherkin
Then a cookie with a name containing "user" and the value "guest" should not exist
```

#### Check if a cookie with a partial name and partial value does not exist

```gherkin
@Then a cookie with a name containing :partial_name and a value containing :partial_value should not exist
```
Example:

```gherkin
Then a cookie with a name containing "user" and a value containing "guest" should not exist
```

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
Example:

```gherkin
When I save the draggable views items of the view "draggableviews_demo" and the display "page_1" for the "article" content in the following order:
  | First Article  |
  | Second Article |
  | Third Article  |
```

### EmailTrait

[Source](src/EmailTrait.php), [Example](tests/behat/features/email.feature)

#### Clear test email system queue

```gherkin
@When I clear the test email system queue
```
Example:

```gherkin
When I clear the test email system queue
```

#### Enable the test email system

```gherkin
@When I enable the test email system
```
Example:

```gherkin
When I enable the test email system
```

#### Follow a specific link number in an email with the given subject

```gherkin
@When I follow link number :link_number in the email with the subject :subject
```
Example:

```gherkin
When I follow link number "1" in the email with the subject "Account Verification"
```

#### Follow a specific link number in an email whose subject contains the given substring

```gherkin
@When I follow link number :link_number in the email with the subject containing :subject
```
Example:

```gherkin
When I follow link number "1" in the email with the subject containing "Verification"
```

#### Disable test email system

```gherkin
@When I disable the test email system
```
Example:

```gherkin
When I disable the test email system
```

#### Assert that an email should be sent to an address

```gherkin
@Then an email should be sent to the :address
```
Example:

```gherkin
Then an email should be sent to the "user@example.com"
```

#### Assert that no email messages should be sent

```gherkin
@Then no emails should be sent
```
Example:

```gherkin
Then no emails should be sent
```

#### Assert that no email messages should be sent to a specified address

```gherkin
@Then no emails should be sent to the :address
```
Example:

```gherkin
Then no emails should be sent to the "user@example.com"
```

#### Assert that the email message header should contain specified content

```gherkin
@Then the email header :header should contain:
```
Example:

```gherkin
Then the email header "Subject" should contain:
"""
Account details
"""
```

#### Assert that the email message header should be the exact specified content

```gherkin
@Then the email header :header should exactly be:
```
Example:

```gherkin
Then the email header "Subject" should exactly be:
"""
Your Account Details
"""
```

#### Assert that an email should be sent to an address with the exact content in the body

```gherkin
@Then an email should be sent to the address :address with the content:
```
Example:

```gherkin
Then an email should be sent to the address "user@example.com" with the content:
"""
Welcome to our site!
Click the link below to verify your account.
"""
```

#### Assert that an email should be sent to an address with the body containing specific content

```gherkin
@Then an email should be sent to the address :address with the content containing:
```
Example:

```gherkin
Then an email should be sent to the address "user@example.com" with the content containing:
"""
verification link
"""
```

#### Assert that an email should be sent to an address with the body not containing specific content

```gherkin
@Then an email should be sent to the address :address with the content not containing:
```
Example:

```gherkin
Then an email should be sent to the address "user@example.com" with the content not containing:
"""
password
"""
```

#### Assert that an email should not be sent to an address with the exact content in the body

```gherkin
@Then an email should not be sent to the address :address with the content:
```
Example:

```gherkin
Then an email should not be sent to the address "wrong@example.com" with the content:
"""
Welcome to our site!
"""
```

#### Assert that an email should not be sent to an address with the body containing specific content

```gherkin
@Then an email should not be sent to the address :address with the content containing:
```
Example:

```gherkin
Then an email should not be sent to the address "wrong@example.com" with the content containing:
"""
verification link
"""
```

#### Assert that the email field should contain a value

```gherkin
@Then the email field :field should contain:
```
Example:

```gherkin
Then the email field "body" should contain:
"""
Please verify your account
"""
```

#### Assert that the email field should exactly match a value

```gherkin
@Then the email field :field should be:
```
Example:

```gherkin
Then the email field "subject" should be:
"""
Account Verification
"""
```

#### Assert that the email field should not contain a value

```gherkin
@Then the email field :field should not contain:
```
Example:

```gherkin
Then the email field "body" should not contain:
"""
password
"""
```

#### Assert that the email field should not exactly match a value

```gherkin
@Then the email field :field should not be:
```
Example:

```gherkin
Then the email field "subject" should not be:
"""
Password Reset
"""
```

#### Assert that a file is attached to an email message with specified subject

```gherkin
@Then the file :file_name should be attached to the email with the subject :subject
```
Example:

```gherkin
Then the file "document.pdf" should be attached to the email with the subject "Your document"
```

#### Assert that a file is attached to an email message with a subject containing the specified substring

```gherkin
@Then the file :file_name should be attached to the email with the subject containing :subject
```
Example:

```gherkin
Then the file "report.xlsx" should be attached to the email with the subject containing "Monthly Report"
```

### ElementTrait

[Source](src/ElementTrait.php), [Example](tests/behat/features/element.feature)

#### Accept confirmation dialogs appearing on the page

```gherkin
@Given I accept all confirmation dialogs
```
Example:

```gherkin
Given I accept all confirmation dialogs
```

#### Do not accept confirmation dialogs appearing on the page

```gherkin
@Given I do not accept any confirmation dialogs
```
Example:

```gherkin
Given I do not accept any confirmation dialogs
```

#### Click on the element defined by the selector

```gherkin
@When I click on the element :selector
```
Example:

```gherkin
When I click on the element ".button"
```

#### When I trigger the JS event :event on the element :selector

```gherkin
@When I trigger the JS event :event on the element :selector
```
Example:

```gherkin
When I trigger the JS event "click" on the element "#submit-button"
```

#### Scroll to an element with ID

```gherkin
@When I scroll to the element :selector
```
Example:

```gherkin
When I scroll to the element "#footer"
```

#### Assert an element with selector and attribute with a value exists

```gherkin
@Then the element :selector with the attribute :attribute and the value :value should exist
```
Example:

```gherkin
Then the element "#main-content" with the attribute "class" and the value "content-wrapper" should exist
```

#### Assert an element with selector and attribute containing a value exists

```gherkin
@Then the element :selector with the attribute :attribute and the value containing :value should exist
```
Example:

```gherkin
Then the element "#main-content" with the attribute "class" and the value containing "content" should exist
```

#### Assert an element with selector and attribute with a value exists

```gherkin
@Then the element :selector with the attribute :attribute and the value :value should not exist
```
Example:

```gherkin
Then the element "#main-content" with the attribute "class" and the value "hidden" should not exist
```

#### Assert an element with selector and attribute containing a value does not exist

```gherkin
@Then the element :selector with the attribute :attribute and the value containing :value should not exist
```
Example:

```gherkin
Then the element "#main-content" with the attribute "class" and the value containing "hidden" should not exist
```

#### Assert the element :selector should be at the top of the viewport

```gherkin
@Then the element :selector should be at the top of the viewport
```
Example:

```gherkin
Then the element "#header" should be at the top of the viewport
```

### FieldTrait

[Source](src/FieldTrait.php), [Example](tests/behat/features/field.feature)

#### Fills value for color field

```gherkin
@When I fill color in :field with :value
```
```gherkin
@When I fill in the color field :field with the value :value
```

#### Set value for WYSIWYG field

```gherkin
@When I fill in the WYSIWYG field :field with the :value
```

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

#### Asserts that a color field has a value

```gherkin
@Then the color field :field should have the value :value
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

#### Assert that the link with a text is absolute

```gherkin
@Then the link :link should be an absolute link
```
Example:

```gherkin
Then the link "Drupal" should be an absolute link
```

#### Assert that the link is not an absolute

```gherkin
@Then the link :link should not be an absolute link
```
Example:

```gherkin
Then the link "Return to site content" should not be an absolute link
```

### MediaTrait

[Source](src/MediaTrait.php), [Example](tests/behat/features/media.feature)

#### Remove media type

```gherkin
@Given "video" media type does not exist
```
```gherkin
@Given :media_type media type does not exist
```

#### Creates media of a given type

```gherkin
@Given the following media :media_type exist:
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
@Given the following media :media_type do not exist:
```
Example:

```gherkin
Given the following media "image" do not exist:
| name               |
| Media item         |
| Another media item |
```

#### Navigate to edit media with specified type and name

```gherkin
@When I edit the media :media_type with the name :name
```
Example:

```gherkin
When I edit "document" media "Test document"
```

### MenuTrait

[Source](src/MenuTrait.php), [Example](tests/behat/features/menu.feature)

#### Remove a single menu by its label if it exists

```gherkin
@Given the menu :menu_name does not exist
```

#### Create a menu if one does not exist

```gherkin
@Given the following menus:
```

#### Remove menu links by title

```gherkin
@Given the following menu links do not exist in the menu :menu_name:
```

#### Create menu links

```gherkin
@Given the following menu links exist in the menu :menu_name :
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
@Then the option :option should exist within the select element :selector
```

#### Assert that a select does not have an option

```gherkin
@Then the option :option should not exist within the select element :selector
```

#### Assert that a select option is selected

```gherkin
@Then the option :option should be selected within the select element :selector
```

#### Assert that a select option is not selected

```gherkin
@Then the option :option should not be selected within the select element :selector
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
@Then the element :selector should be displayed
```

#### Assert that element with specified CSS is visible on page

```gherkin
@Then the element :selector should not be displayed
```

#### Assert that element with specified CSS is displayed within a viewport

```gherkin
@Then the element :selector should be displayed within a viewport
```

#### Assert that element with specified CSS is displayed within a viewport with a top offset

```gherkin
@Then the element :selector should be displayed within a viewport with a top offset of :number pixels
```

#### Assert that element with specified CSS is not displayed within a viewport with a top offset

```gherkin
@Then the element :selector should not be displayed within a viewport with a top offset of :number pixels
```

#### Assert that element with specified CSS is visually hidden on page

```gherkin
@Then the element :selector should not be displayed within a viewport
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
