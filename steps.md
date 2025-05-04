# Available steps

| Class | Description |
| --- | --- |
| [BlockTrait](#blocktrait) | Create, configure, and test block. |
| [ContentBlockTrait](#contentblocktrait) | Manages content block entities. |
| [ContentTrait](#contenttrait) | Works with Drupal content entities. |
| [CookieTrait](#cookietrait) | Works with browser cookies. |
| [DraggableviewsTrait](#draggableviewstrait) | Tests Drupal's Draggable Views module functionality. |
| [EckTrait](#ecktrait) | Tests Drupal's Entity Construction Kit (ECK) module. |
| [ElementTrait](#elementtrait) | Interacts with and validates HTML elements. |
| [EmailTrait](#emailtrait) | Tests email functionality in Drupal applications. |
| [FieldTrait](#fieldtrait) | Interacts with and validates form fields. |
| [FileDownloadTrait](#filedownloadtrait) | Downloads and validates files during tests. |
| [FileTrait](#filetrait) | Creates and manages Drupal files in tests. |
| [KeyboardTrait](#keyboardtrait) | Simulates keyboard interactions in browser tests. |
| [LinkTrait](#linktrait) | Interacts with and validates HTML links. |
| [MediaTrait](#mediatrait) | Creates and tests Drupal media entities. |
| [MenuTrait](#menutrait) | Creates and manages Drupal menus and menu items. |
| [ParagraphsTrait](#paragraphstrait) | Tests Drupal Paragraphs module functionality. |
| [PathTrait](#pathtrait) | Tests URL paths and basic authentication. |
| [ResponseTrait](#responsetrait) | Tests HTTP response headers in web requests. |
| [RoleTrait](#roletrait) | Creates and manages Drupal user roles. |
| [SearchApiTrait](#searchapitrait) | Tests Drupal Search API module functionality. |
| [SelectTrait](#selecttrait) | Tests HTML select elements and their options. |
| [TaxonomyTrait](#taxonomytrait) | Tests Drupal taxonomy terms and vocabularies. |
| [UserTrait](#usertrait) | Tests Drupal users, authentication, and profiles. |
| [WaitTrait](#waittrait) | Implements timed waits and AJAX completion checks. |
## BlockTrait

[Source](src/BlockTrait.php), [Example](tests/behat/features/block.feature)

<details>
  <summary><code>@Given the instance of :admin_label block exists with the following configuration:</code></summary>

```gherkin
Given the instance of "My block" block exists with the following configuration:
 | label         | My block |
 | label_display | 1        |
 | region        | content  |
 | status        | 1        |

```
</details>

<details>
  <summary><code>@Given the block :label has the following configuration:</code></summary>

```gherkin
Given the block "My block" has the following configuration:
| label_display | 1       |
| region        | content |
| status        | 1       |

```
</details>

<details>
  <summary><code>@Given the block :label does not exist</code></summary>

```gherkin
Given the block "My block" does not exist

```
</details>

<details>
  <summary><code>@Given the block :label is enabled</code></summary>

```gherkin
Given the block "My block" is enabled

```
</details>

<details>
  <summary><code>@Given the block :label is disabled</code></summary>

```gherkin
Given the block "My block" is disabled

```
</details>

<details>
  <summary><code>@Given the block :label has the following :condition condition configuration:</code></summary>

```gherkin
Given the block "My block" has the following "request_path" condition configuration:
| pages  | /node/1\r\n/about |
| negate | 0                 |

```
</details>

<details>
  <summary><code>@Given the block :label has the :condition condition removed</code></summary>

```gherkin
Given the block "My block" has the "request_path" condition removed

```
</details>

<details>
  <summary><code>@Then the block :label should exist</code></summary>

```gherkin
Then the block "My block" should exist

```
</details>

<details>
  <summary><code>@Then the block :label should not exist</code></summary>

```gherkin
Then the block "My block" should not exist

```
</details>

<details>
  <summary><code>@Then the block :label should exist in the :region region</code></summary>

```gherkin
Then the block "My block" should exist in the "content" region

```
</details>

<details>
  <summary><code>@Then the block :label should not exist in the :region region</code></summary>

```gherkin
Then the block "My block" should not exist in the "content" region

```
</details>

## ContentBlockTrait

[Source](src/ContentBlockTrait.php), [Example](tests/behat/features/content_block.feature)

<details>
  <summary><code>@Given the following :type content blocks do not exist:</code></summary>

```gherkin
Given the following "basic" content blocks do not exist:
| [TEST] Footer Block  |
| [TEST] Contact Form  |

```
</details>

<details>
  <summary><code>@Given the following :type content blocks exist:</code></summary>

```gherkin
Given the following "basic" content blocks exist:
| info                  | status | body                   | created           |
| [TEST] Footer Contact | 1      | Call us at 555-1234    | 2023-01-17 8:00am |
| [TEST] Copyright      | 1      | © 2023 Example Company | 2023-01-18 9:00am |

```
</details>

<details>
  <summary><code>@When I edit the :type content block with the description :description</code></summary>

```gherkin
When I edit the "basic" content block with the description "[TEST] Footer Block"

```
</details>

<details>
  <summary><code>@Then the content block type :type should exist</code></summary>

```gherkin
Then the content block type "Search" should exist

```
</details>

## ContentTrait

[Source](src/ContentTrait.php), [Example](tests/behat/features/content.feature)

<details>
  <summary><code>@Given the content type :content_type does not exist</code></summary>

```gherkin
Given the content type "article" does not exist

```
</details>

<details>
  <summary><code>@Given the following :content_type content does not exist:</code></summary>

```gherkin
Given the following "article" content does not exist:
  | title                |
  | Test article         |
  | Another test article |

```
</details>

<details>
  <summary><code>@When I visit the :content_type content page with the title :title</code></summary>

```gherkin
When I visit the "article" content page with the title "Test article"

```
</details>

<details>
  <summary><code>@When I visit the :content_type content edit page with the title :title</code></summary>

```gherkin
When I visit the "article" content edit page with the title "Test article"

```
</details>

<details>
  <summary><code>@When I visit the :content_type content delete page with the title :title</code></summary>

```gherkin
When I visit the "article" content delete page with the title "Test article"

```
</details>

<details>
  <summary><code>@When I visit the :content_type content scheduled transitions page with the title :title</code></summary>

```gherkin
When I visit the "article" content scheduled transitions page with the title "Test article"

```
</details>

<details>
  <summary><code>@When I change the moderation state of the :content_type content with the title :title to the :new_state state</code></summary>

```gherkin
When I change the moderation state of the "article" content with the title "Test article" to the "published" state

```
</details>

## CookieTrait

[Source](src/CookieTrait.php), [Example](tests/behat/features/cookie.feature)

<details>
  <summary><code>@Then a cookie with the name :name should exist</code></summary>

```gherkin
Then a cookie with the name "session_id" should exist

```
</details>

<details>
  <summary><code>@Then a cookie with the name :name and the value :value should exist</code></summary>

```gherkin
Then a cookie with the name "language" and the value "en" should exist

```
</details>

<details>
  <summary><code>@Then a cookie with the name :name and a value containing :partial_value should exist</code></summary>

```gherkin
Then a cookie with the name "preferences" and a value containing "darkmode" should exist

```
</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name should exist</code></summary>

```gherkin
Then a cookie with a name containing "session" should exist

```
</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and the value :value should exist</code></summary>

```gherkin
Then a cookie with a name containing "user" and the value "admin" should exist

```
</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and a value containing :partial_value should exist</code></summary>

```gherkin
Then a cookie with a name containing "user" and a value containing "admin" should exist

```
</details>

<details>
  <summary><code>@Then a cookie with the name :name should not exist</code></summary>

```gherkin
Then a cookie with name "old_session" should not exist

```
</details>

<details>
  <summary><code>@Then a cookie with the name :name and the value :value should not exist</code></summary>

```gherkin
Then a cookie with the name "language" and the value "fr" should not exist

```
</details>

<details>
  <summary><code>@Then a cookie with the name :name and a value containing :partial_value should not exist</code></summary>

```gherkin
Then a cookie with the name "preferences" and a value containing "lightmode" should not exist

```
</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name should not exist</code></summary>

```gherkin
Then a cookie with a name containing "old" should not exist

```
</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and the value :value should not exist</code></summary>

```gherkin
Then a cookie with a name containing "user" and the value "guest" should not exist

```
</details>

<details>
  <summary><code>@Then a cookie with a name containing :partial_name and a value containing :partial_value should not exist</code></summary>

```gherkin
Then a cookie with a name containing "user" and a value containing "guest" should not exist

```
</details>

## DraggableviewsTrait

[Source](src/DraggableviewsTrait.php), [Example](tests/behat/features/draggableviews.feature)

<details>
  <summary><code>@When I save the draggable views items of the view :view_id and the display :views_display_id for the :bundle content in the following order:</code></summary>

```gherkin
When I save the draggable views items of the view "draggableviews_demo" and the display "page_1" for the "article" content in the following order:
  | First Article  |
  | Second Article |
  | Third Article  |

```
</details>

## EckTrait

[Source](src/EckTrait.php), [Example](tests/behat/features/eck.feature)

<details>
  <summary><code>@Given the following eck :bundle :entity_type entities exist:</code></summary>

```gherkin
Given the following eck "contact" "contact_type" entities exist:
| title  | field_marine_animal     | field_fish_type | ... |
| Snook  | Fish                    | Marine fish     | 10  |
| ...    | ...                     | ...             | ... |

```
</details>

<details>
  <summary><code>@Given the following eck :bundle :entity_type entities do not exist:</code></summary>

```gherkin
Given the following eck "contact" "contact_type" entities do not exist:
| field        | value           |
| field_a      | Entity label    |

```
</details>

<details>
  <summary><code>@When I visit eck :bundle :entity_type entity with the title :title</code></summary>

```gherkin
When I visit eck "contact" "contact_type" entity with the title "Test contact"

```
</details>

<details>
  <summary><code>@When I edit eck :bundle :entity_type entity with the title :title</code></summary>

```gherkin
When I edit eck "contact" "contact_type" entity with the title "Test contact"

```
</details>

## ElementTrait

[Source](src/ElementTrait.php), [Example](tests/behat/features/element.feature)

<details>
  <summary><code>@Given I accept all confirmation dialogs</code></summary>

```gherkin
Given I accept all confirmation dialogs

```
</details>

<details>
  <summary><code>@Given I do not accept any confirmation dialogs</code></summary>

```gherkin
Given I do not accept any confirmation dialogs

```
</details>

<details>
  <summary><code>@When I click on the element :selector</code></summary>

```gherkin
When I click on the element ".button"

```
</details>

<details>
  <summary><code>@When I trigger the JS event :event on the element :selector</code></summary>

```gherkin
When I trigger the JS event "click" on the element "#submit-button"

```
</details>

<details>
  <summary><code>@When I scroll to the element :selector</code></summary>

```gherkin
When I scroll to the element "#footer"

```
</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and the value :value should exist</code></summary>

```gherkin
Then the element "#main-content" with the attribute "class" and the value "content-wrapper" should exist

```
</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and the value containing :value should exist</code></summary>

```gherkin
Then the element "#main-content" with the attribute "class" and the value containing "content" should exist

```
</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and the value :value should not exist</code></summary>

```gherkin
Then the element "#main-content" with the attribute "class" and the value "hidden" should not exist

```
</details>

<details>
  <summary><code>@Then the element :selector with the attribute :attribute and the value containing :value should not exist</code></summary>

```gherkin
Then the element "#main-content" with the attribute "class" and the value containing "hidden" should not exist

```
</details>

<details>
  <summary><code>@Then the element :selector should be at the top of the viewport</code></summary>

```gherkin
Then the element "#header" should be at the top of the viewport

```
</details>

<details>
  <summary><code>@Then the element :selector should be displayed</code></summary>

```gherkin
Then the element ".alert-success" should be displayed

```
</details>

<details>
  <summary><code>@Then the element :selector should not be displayed</code></summary>

```gherkin
Then the element ".error-message" should not be displayed

```
</details>

<details>
  <summary><code>@Then the element :selector should be displayed within a viewport</code></summary>

```gherkin
Then the element ".hero-banner" should be displayed within a viewport

```
</details>

<details>
  <summary><code>@Then the element :selector should be displayed within a viewport with a top offset of :number pixels</code></summary>

```gherkin
Then the element ".sticky-header" should be displayed within a viewport with a top offset of 50 pixels

```
</details>

<details>
  <summary><code>@Then the element :selector should not be displayed within a viewport with a top offset of :number pixels</code></summary>

```gherkin
Then the element ".below-fold-content" should not be displayed within a viewport with a top offset of 0 pixels

```
</details>

<details>
  <summary><code>@Then the element :selector should not be displayed within a viewport</code></summary>

```gherkin
Then the element ".visually-hidden" should not be displayed within a viewport

```
</details>

## EmailTrait

[Source](src/EmailTrait.php), [Example](tests/behat/features/email.feature)

<details>
  <summary><code>@When I clear the test email system queue</code></summary>

```gherkin
When I clear the test email system queue

```
</details>

<details>
  <summary><code>@When I follow link number :link_number in the email with the subject :subject</code></summary>

```gherkin
When I follow link number "1" in the email with the subject "Account Verification"

```
</details>

<details>
  <summary><code>@When I follow link number :link_number in the email with the subject containing :subject</code></summary>

```gherkin
When I follow link number "1" in the email with the subject containing "Verification"

```
</details>

<details>
  <summary><code>@When I enable the test email system</code></summary>

```gherkin
When I enable the test email system

```
</details>

<details>
  <summary><code>@When I disable the test email system</code></summary>

```gherkin
When I disable the test email system

```
</details>

<details>
  <summary><code>@Then an email should be sent to the :address</code></summary>

```gherkin
Then an email should be sent to the "user@example.com"

```
</details>

<details>
  <summary><code>@Then no emails should have been sent</code></summary>

```gherkin
Then no emails should have been sent

```
</details>

<details>
  <summary><code>@Then no emails should have been sent to the :address</code></summary>

```gherkin
Then no emails should have been sent to the "user@example.com"

```
</details>

<details>
  <summary><code>@Then the email header :header should contain:</code></summary>

```gherkin
Then the email header "Subject" should contain:
"""
Account details
"""

```
</details>

<details>
  <summary><code>@Then the email header :header should exactly be:</code></summary>

```gherkin
Then the email header "Subject" should exactly be:
"""
Your Account Details
"""

```
</details>

<details>
  <summary><code>@Then an email should be sent to the address :address with the content:</code></summary>

```gherkin
Then an email should be sent to the address "user@example.com" with the content:
"""
Welcome to our site!
Click the link below to verify your account.
"""

```
</details>

<details>
  <summary><code>@Then an email should be sent to the address :address with the content containing:</code></summary>

```gherkin
Then an email should be sent to the address "user@example.com" with the content containing:
"""
verification link
"""

```
</details>

<details>
  <summary><code>@Then an email should be sent to the address :address with the content not containing:</code></summary>

```gherkin
Then an email should be sent to the address "user@example.com" with the content not containing:
"""
password
"""

```
</details>

<details>
  <summary><code>@Then an email should not be sent to the address :address with the content:</code></summary>

```gherkin
Then an email should not be sent to the address "wrong@example.com" with the content:
"""
Welcome to our site!
"""

```
</details>

<details>
  <summary><code>@Then an email should not be sent to the address :address with the content containing:</code></summary>

```gherkin
Then an email should not be sent to the address "wrong@example.com" with the content containing:
"""
verification link
"""

```
</details>

<details>
  <summary><code>@Then the email field :field should contain:</code></summary>

```gherkin
Then the email field "body" should contain:
"""
Please verify your account
"""

```
</details>

<details>
  <summary><code>@Then the email field :field should be:</code></summary>

```gherkin
Then the email field "subject" should be:
"""
Account Verification
"""

```
</details>

<details>
  <summary><code>@Then the email field :field should not contain:</code></summary>

```gherkin
Then the email field "body" should not contain:
"""
password
"""

```
</details>

<details>
  <summary><code>@Then the email field :field should not be:</code></summary>

```gherkin
Then the email field "subject" should not be:
"""
Password Reset
"""

```
</details>

<details>
  <summary><code>@Then the file :file_name should be attached to the email with the subject :subject</code></summary>

```gherkin
Then the file "document.pdf" should be attached to the email with the subject "Your document"

```
</details>

<details>
  <summary><code>@Then the file :file_name should be attached to the email with the subject containing :subject</code></summary>

```gherkin
Then the file "report.xlsx" should be attached to the email with the subject containing "Monthly Report"

```
</details>

## FieldTrait

[Source](src/FieldTrait.php), [Example](tests/behat/features/field.feature)

<details>
  <summary><code>@When I fill in the color field :field with the value :value</code></summary>

```gherkin
When I fill in the color field "#edit-text-color" with the value "#3366FF"

```
</details>

<details>
  <summary><code>@When I fill in the WYSIWYG field :field with the :value</code></summary>

```gherkin
When I fill in the WYSIWYG field "edit-body-0-value" with the "<p>This is a <strong>formatted</strong> paragraph.</p>"

```
</details>

<details>
  <summary><code>@Then the field :name should exist</code></summary>

```gherkin
Then the field "Body" should exist
Then the field "field_body" should exist

```
</details>

<details>
  <summary><code>@Then the field :name should not exist</code></summary>

```gherkin
Then the field "Body" should not exist
Then the field "field_body" should not exist

```
</details>

<details>
  <summary><code>@Then the field :name should be :enabled_or_disabled</code></summary>

```gherkin
Then the field "Body" should be "disabled"
Then the field "field_body" should be "disabled"
Then the field "Tags" should be "enabled"
Then the field "field_tags" should be "not enabled"

```
</details>

<details>
  <summary><code>@Then the color field :field should have the value :value</code></summary>

```gherkin
Then the color field "#edit-background-color" should have the value "#FF5733"

```
</details>

## FileDownloadTrait

[Source](src/FileDownloadTrait.php), [Example](tests/behat/features/file_download.feature)

<details>
  <summary><code>@When I download the file from the URL :url</code></summary>

```gherkin
When I download the file from the URL "/sites/default/files/document.pdf"
When I download the file from the URL "https://example.com/files/report.xlsx"

```
</details>

<details>
  <summary><code>@When I download the file from the link :link</code></summary>

```gherkin
When I download the file from the link "Download PDF"
When I download the file from the link "Get Report"

```
</details>

<details>
  <summary><code>@Then the downloaded file should contain:</code></summary>

```gherkin
Then the downloaded file should contain:
"""
Financial Report 2023
"""

```
</details>

<details>
  <summary><code>@Then the downloaded file name should be :name</code></summary>

```gherkin
Then the downloaded file name should be "report.pdf"

```
</details>

<details>
  <summary><code>@Then the downloaded file name should contain :name</code></summary>

```gherkin
Then the downloaded file name should contain "report"

```
</details>

<details>
  <summary><code>@Then the downloaded file should be a zip archive containing the files named:</code></summary>

```gherkin
Then the downloaded file should be a zip archive containing the files named:
| document.pdf |
| image.jpg    |
| data.csv     |

```
</details>

<details>
  <summary><code>@Then the downloaded file should be a zip archive containing the files partially named:</code></summary>

```gherkin
Then the downloaded file should be a zip archive containing the files partially named:
| report |
| data   |
| image  |

```
</details>

<details>
  <summary><code>@Then the downloaded file should be a zip archive not containing the files partially named:</code></summary>

```gherkin
Then the downloaded file should be a zip archive not containing the files partially named:
| confidential |
| private      |
| draft        |

```
</details>

## FileTrait

[Source](src/FileTrait.php), [Example](tests/behat/features/file.feature)

<details>
  <summary><code>@Given the following managed files:</code></summary>

```gherkin
Given the following managed files:
| path         | uri                    | status |
| document.pdf | public://document.pdf  | 1      |
| image.jpg    | public://images/pic.jpg| 1      |

```
</details>

<details>
  <summary><code>@Given the following managed files do not exist:</code></summary>

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
</details>

<details>
  <summary><code>@Given the unmanaged file at the URI :uri exists</code></summary>

```gherkin
Given the unmanaged file at the URI "public://sample.txt" exists

```
</details>

<details>
  <summary><code>@Given the unmanaged file at the URI :uri exists with :content</code></summary>

```gherkin
Given the unmanaged file at the URI "public://data.txt" exists with "Sample content"

```
</details>

<details>
  <summary><code>@Then an unmanaged file at the URI :uri should exist</code></summary>

```gherkin
Then an unmanaged file at the URI "public://sample.txt" should exist

```
</details>

<details>
  <summary><code>@Then an unmanaged file at the URI :uri should not exist</code></summary>

```gherkin
Then an unmanaged file at the URI "public://temp.txt" should not exist

```
</details>

<details>
  <summary><code>@Then an unmanaged file at the URI :uri should contain :content</code></summary>

```gherkin
Then an unmanaged file at the URI "public://config.txt" should contain "debug=true"

```
</details>

<details>
  <summary><code>@Then an unmanaged file at the URI :uri should not contain :content</code></summary>

```gherkin
Then an unmanaged file at the URI "public://config.txt" should not contain "debug=false"

```
</details>

## KeyboardTrait

[Source](src/KeyboardTrait.php), [Example](tests/behat/features/keyboard.feature)

<details>
  <summary><code>@When I press the key :key</code></summary>

```gherkin
When I press the key "a"
When I press the key "tab"

```
</details>

<details>
  <summary><code>@When I press the key :key on the element :selector</code></summary>

```gherkin
When I press the key "a" on the element "#edit-title"
When I press the key "tab" on the element "#edit-title"

```
</details>

<details>
  <summary><code>@When I press the keys :keys</code></summary>

```gherkin
When I press the keys "abc"

```
</details>

<details>
  <summary><code>@When I press the keys :keys on the element :selector</code></summary>

```gherkin
When I press the keys "abc" on the element "#edit-title"

```
</details>

## LinkTrait

[Source](src/LinkTrait.php), [Example](tests/behat/features/link.feature)

<details>
  <summary><code>@When I click on the link with the title :title</code></summary>

```gherkin
When I click on the link with the title "Return to site content"

```
</details>

<details>
  <summary><code>@Then the link :link with the href :href should exist</code></summary>

```gherkin
Then the link "About us" with the href "/about-us" should exist
Then the link "About us" with the href "/about*" should exist

```
</details>

<details>
  <summary><code>@Then the link :link with the href :href within the element :selector should exist</code></summary>

```gherkin
Then the link "About us" with the href "/about-us" within the element ".main-nav" should exist
Then the link "About us" with the href "/about*" within the element ".main-nav" should exist

```
</details>

<details>
  <summary><code>@Then the link :link with the href :href should not exist</code></summary>

```gherkin
Then the link "About us" with the href "/about-us" should not exist
Then the link "About us" with the href "/about*" should not exist

```
</details>

<details>
  <summary><code>@Then the link :link with the href :href within the element :selector should not exist</code></summary>

```gherkin
Then the link "About us" with the href "/about-us" within the element ".main-nav" should not exist
Then the link "About us" with the href "/about*" within the element ".main-nav" should not exist

```
</details>

<details>
  <summary><code>@Then the link with the title :title should exist</code></summary>

```gherkin
Then the link with the title "Return to site content" should exist

```
</details>

<details>
  <summary><code>@Then the link with the title :title should not exist</code></summary>

```gherkin
Then the link with the title "Some non-existing title" should not exist

```
</details>

<details>
  <summary><code>@Then the link :link should be an absolute link</code></summary>

```gherkin
Then the link "my-link-title" should be an absolute link

```
</details>

<details>
  <summary><code>@Then the link :link should not be an absolute link</code></summary>

```gherkin
Then the link "Return to site content" should not be an absolute link

```
</details>

## MediaTrait

[Source](src/MediaTrait.php), [Example](tests/behat/features/media.feature)

<details>
  <summary><code>@Given :media_type media type does not exist</code></summary>

```gherkin
Given "video" media type does not exist

```
</details>

<details>
  <summary><code>@Given the following media :media_type exist:</code></summary>

```gherkin
Given "video" media:
| name     | field1   | field2 | field3           |
| My media | file.jpg | value  | value            |
| ...      | ...      | ...    | ...              |

```
</details>

<details>
  <summary><code>@Given the following media :media_type do not exist:</code></summary>

```gherkin
Given the following media "image" do not exist:
| name               |
| Media item         |
| Another media item |

```
</details>

<details>
  <summary><code>@When I edit the media :media_type with the name :name</code></summary>

```gherkin
When I edit "document" media "Test document"

```
</details>

## MenuTrait

[Source](src/MenuTrait.php), [Example](tests/behat/features/menu.feature)

<details>
  <summary><code>@Given the menu :menu_name does not exist</code></summary>

```gherkin
Given the menu "Test Menu" does not exist

```
</details>

<details>
  <summary><code>@Given the following menus:</code></summary>

```gherkin
Given the following menus:
| label            | description                    |
| Footer Menu     | Links displayed in the footer  |
| Secondary Menu  | Secondary navigation menu      |

```
</details>

<details>
  <summary><code>@Given the following menu links do not exist in the menu :menu_name:</code></summary>

```gherkin
Given the following menu links do not exist in the menu "Main navigation":
| About Us     |
| Contact      |

```
</details>

<details>
  <summary><code>@Given the following menu links exist in the menu :menu_name :</code></summary>

```gherkin
Given the following menu links exist in the menu "Main navigation":
| title           | enabled | uri                     | parent       |
| Products        | 1       | /products               |              |
| Latest Products | 1       | /products/latest        | Products     |

```
</details>

## ParagraphsTrait

[Source](src/ParagraphsTrait.php), [Example](tests/behat/features/paragraphs.feature)

<details>
  <summary><code>@Given the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:</code></summary>

```gherkin
Given the following fields for the paragraph "text" exist in the field "field_component" within the "landing_page" "node" identified by the field "title" and the value "My landing page":
| field_paragraph_title           | My paragraph title   |
| field_paragraph_longtext:value  | My paragraph message |
| field_paragraph_longtext:format | full_html            |
| ...                             | ...                  |

```
</details>

## PathTrait

[Source](src/PathTrait.php), [Example](tests/behat/features/path.feature)

<details>
  <summary><code>@Given the basic authentication with the username :username and the password :password</code></summary>

```gherkin
Given the basic authentication with the username "myusername" and the password "mypassword"

```
</details>

<details>
  <summary><code>@Then the path should be :path</code></summary>

```gherkin
Then the path should be "/about-us"
Then the path should be "<front>"

```
</details>

<details>
  <summary><code>@Then the path should not be :path</code></summary>

```gherkin
Then the path should not be "/about-us"
Then the path should not be "<front>"

```
</details>

## ResponseTrait

[Source](src/ResponseTrait.php), [Example](tests/behat/features/response.feature)

<details>
  <summary><code>@Then the response should contain the header :header_name</code></summary>

```gherkin
Then the response should contain the header "Connection"

```
</details>

<details>
  <summary><code>@Then the response should not contain the header :header_name</code></summary>

```gherkin
Then the response should not contain the header "Connection"

```
</details>

<details>
  <summary><code>@Then the response header :header_name should contain the value :header_value</code></summary>

```gherkin
Then the response header "Connection" should contain the value "Keep-Alive"

```
</details>

<details>
  <summary><code>@Then the response header :header_name should not contain the value :header_value</code></summary>

```gherkin
Then the response header "Connection" should not contain the value "Keep-Alive"

```
</details>

## RoleTrait

[Source](src/RoleTrait.php), [Example](tests/behat/features/role.feature)

<details>
  <summary><code>@Given the role :role_name with the permissions :permissions</code></summary>

```gherkin
Given the role "Content Manager" with the permissions "access content, create article content, edit any article content"

```
</details>

<details>
  <summary><code>@Given the following roles:</code></summary>

```gherkin
Given the following roles:
| name              | permissions                                         |
| Content Editor   | access content, create article content              |
| Content Approver | access content, edit any article content            |

```
</details>

## SearchApiTrait

[Source](src/SearchApiTrait.php), [Example](tests/behat/features/search_api.feature)

<details>
  <summary><code>@When I add the :content_type content with the title :title to the search index</code></summary>

```gherkin
When I add the "article" content with the title "Test Article" to the search index

```
</details>

<details>
  <summary><code>@When I run search indexing for :count item(s)</code></summary>

```gherkin
When I run search indexing for 5 items
When I run search indexing for 1 item

```
</details>

## SelectTrait

[Source](src/SelectTrait.php), [Example](tests/behat/features/select.feature)

<details>
  <summary><code>@Then the option :option should exist within the select element :selector</code></summary>

```gherkin
Then the option "Administrator" should exist within the select element "edit-roles"

```
</details>

<details>
  <summary><code>@Then the option :option should not exist within the select element :selector</code></summary>

```gherkin
Then the option "Guest" should not exist within the select element "edit-roles"

```
</details>

<details>
  <summary><code>@Then the option :option should be selected within the select element :selector</code></summary>

```gherkin
Then the option "Administrator" should be selected within the select element "edit-roles"

```
</details>

<details>
  <summary><code>@Then the option :option should not be selected within the select element :selector</code></summary>

```gherkin
Then the option "Editor" should not be selected within the select element "edit-roles"

```
</details>

## TaxonomyTrait

[Source](src/TaxonomyTrait.php), [Example](tests/behat/features/taxonomy.feature)

<details>
  <summary><code>@Given the following :vocabulary_machine_name vocabulary terms do not exist:</code></summary>

```gherkin
Given the following "fruits" vocabulary terms do not exist:
  | Apple |
  | Pear  |

```
</details>

<details>
  <summary><code>@When I visit the :vocabulary_machine_name vocabulary :term_name term page</code></summary>

```gherkin
When I visit the "fruits" vocabulary "Apple" term page

```
</details>

<details>
  <summary><code>@When I edit the :vocabulary_machine_name vocabulary :term_name term page</code></summary>

```gherkin
When I edit the "fruits" vocabulary "Apple" term page

```
</details>

<details>
  <summary><code>@Then the vocabulary :machine_name with the name :name should exist</code></summary>

```gherkin
Then the vocabulary "topics" with the name "Topics" should exist

```
</details>

<details>
  <summary><code>@Then the vocabulary :machine_name should not exist</code></summary>

```gherkin
Then the vocabulary "topics" should not exist

```
</details>

<details>
  <summary><code>@Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist</code></summary>

```gherkin
Then the taxonomy term "Apple" from the vocabulary "Fruits" should exist

```
</details>

<details>
  <summary><code>@Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist</code></summary>

```gherkin
Then the taxonomy term "Apple" from the vocabulary "Fruits" should not exist

```
</details>

## UserTrait

[Source](src/UserTrait.php), [Example](tests/behat/features/user.feature)

<details>
  <summary><code>@Given the following users do not exist:</code></summary>

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
</details>

<details>
  <summary><code>@Given the password for the user :name is :password</code></summary>

```gherkin
Given the password for the user "John" is "password"

```
</details>

<details>
  <summary><code>@Given the last access time for the user :name is :datetime</code></summary>

```gherkin
Given the last access time for the user "John" is "Friday, 22 November 2024 13:46:14"
Given the last access time for the user "John" is "1732319174"

```
</details>

<details>
  <summary><code>@Given the last login time for the user :name is :datetime</code></summary>

```gherkin
Given the last login time for the user "John" is "Friday, 22 November 2024 13:46:14"
Given the last login time for the user "John" is "1732319174"

```
</details>

<details>
  <summary><code>@When I visit :name user profile page</code></summary>

```gherkin
When I visit "John" user profile page

```
</details>

<details>
  <summary><code>@When I visit my own user profile page</code></summary>

```gherkin
When I visit my own user profile page

```
</details>

<details>
  <summary><code>@When I visit :name user profile edit page</code></summary>

```gherkin
When I visit "John" user profile edit page

```
</details>

<details>
  <summary><code>@When I visit my own user profile edit page</code></summary>

```gherkin
When I visit my own user profile edit page

```
</details>

<details>
  <summary><code>@When I visit :name user profile delete page</code></summary>

```gherkin
When I visit "John" user profile delete page

```
</details>

<details>
  <summary><code>@When I visit my own user profile delete page</code></summary>

```gherkin
When I visit my own user profile delete page

```
</details>

<details>
  <summary><code>@Then the user :name should have the role(s) :roles assigned</code></summary>

```gherkin
Then the user "John" should have the roles "administrator, editor" assigned

```
</details>

<details>
  <summary><code>@Then the user :name should not have the role(s) :roles assigned</code></summary>

```gherkin
Then the user "John" should not have the roles "administrator, editor" assigned

```
</details>

<details>
  <summary><code>@Then the user :name should be blocked</code></summary>

```gherkin
Then the user "John" should be blocked

```
</details>

<details>
  <summary><code>@Then the user :name should not be blocked</code></summary>

```gherkin
Then the user "John" should not be blocked

```
</details>

## WaitTrait

[Source](src/WaitTrait.php), [Example](tests/behat/features/wait.feature)

<details>
  <summary><code>@When I wait for :seconds second(s)</code></summary>

```gherkin
When I wait for 5 seconds
When I wait for 1 second

```
</details>

<details>
  <summary><code>@When I wait for :seconds second(s) for AJAX to finish</code></summary>

```gherkin
When I wait for 5 seconds for AJAX to finish
When I wait for 1 second for AJAX to finish

```
</details>



[//]: # (END)
