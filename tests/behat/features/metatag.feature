Feature: Check that MetatagTrait works
  As Behat Steps library developer
  I want to provide tools to verify metatags on pages
  So that users can test SEO implementation

  Scenario: Assert that "Then the meta tag with the following attributes should exist:" step works as expected
    When I visit "/"
    Then the meta tag with the following attributes should exist:
      | name    | MobileOptimized |
      | content | width           |

  @test-trait:MetatagTrait
  Scenario: Assert that negative assertion for "Then the meta tag with the following attributes should exist:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the meta tag with the following attributes should exist:
        | name    | Non_Existing |
        | content | width        |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with attributes "{"name":"Non_Existing","content":"width"}" not found.
      """

  Scenario: Assert that "Then the meta tag with the following attributes should not exist:" step works as expected
    When I visit "/"
    Then the meta tag with the following attributes should not exist:
      | name    | Non_Existing |
      | content | width        |

  @test-trait:MetatagTrait
  Scenario: Assert that negative assertion for "Then the meta tag with the following attributes should not exist:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the meta tag with the following attributes should not exist:
        | name    | MobileOptimized |
        | content | width           |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The meta tag with the attributes "{"name":"MobileOptimized","content":"width"}" exists, but it should not.
      """

  @phpserver
  Scenario: Assert "Then the meta tag :name should not contain any HTML tags" works for clean meta tag
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags.html"
    Then the meta tag "description" should not contain any HTML tags

  @phpserver
  Scenario: Assert "Then the meta tag :name should not contain any HTML tags" works for clean OG meta tag
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags.html"
    Then the meta tag "og:title" should not contain any HTML tags

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the meta tag :name should not contain any HTML tags" fails when meta tag contains HTML
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the meta tag "og:description" should not contain any HTML tags
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The meta tag "og:description" contains HTML tags:
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the meta tag :name should not contain any HTML tags" fails when meta tag does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the meta tag "nonexistent" should not contain any HTML tags
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with name|property "nonexistent" not found.
      """

  @phpserver
  Scenario: Assert canonical URL presence and value
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_seo.html"
    Then the canonical URL should exist
    And the canonical URL should be "http://cli:8888/metatags_seo.html"

  @phpserver
  Scenario: Assert canonical URL absence
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags.html"
    Then the canonical URL should not exist

  @phpserver
  Scenario: Assert indexability and robots directives via the robots meta tag
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_seo.html"
    Then the page should be indexable
    And the robots meta tag should contain "index"
    And the robots meta tag should not contain "noindex"

  @phpserver
  Scenario: Assert a non-indexable page via the robots meta tag
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_noindex.html"
    Then the page should not be indexable
    And the robots meta tag should contain "noindex"

  Scenario: Assert a non-indexable page via the X-Robots-Tag header
    When I visit "/mysite_core/test-robots-header"
    Then the page should not be indexable

  Scenario: Assert an indexable page with a non-noindex X-Robots-Tag header
    When I visit "/mysite_core/test-robots-header?value=all"
    Then the page should be indexable

  @phpserver
  Scenario: Assert hreflang alternates are valid
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_seo.html"
    Then the hreflang alternates should be valid

  @phpserver
  Scenario: Assert hreflang alternates have reciprocal return links
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_hreflang_en.html"
    Then the hreflang alternates should be valid
    And the hreflang alternates should have reciprocal return links

  @phpserver
  Scenario: Assert Open Graph tags are valid and present
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_seo.html"
    Then the Open Graph tags should be valid
    And the following Open Graph tags should exist:
      | og:title       |
      | og:description |

  @phpserver
  Scenario: Assert Twitter Card tags are valid and present
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_seo.html"
    Then the Twitter Card tags should be valid
    And the following Twitter Card tags should exist:
      | twitter:image |

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should be" fails on mismatch
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_seo.html"
      Then the canonical URL should be "/wrong-url"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL is "http://cli:8888/metatags_seo.html", but expected "/wrong-url".
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should be" fails when absent
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the canonical URL should be "/some-url"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL is not set.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should exist" fails when absent
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the canonical URL should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL is not set.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should not exist" fails when present
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_seo.html"
      Then the canonical URL should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL should not be set, but found "http://cli:8888/metatags_seo.html".
      """

  @phpserver
  Scenario: Assert canonical URL absence for an empty canonical link
    Given I am an anonymous user
    When I visit "http://cli:8888/metatags_canonical_empty.html"
    Then the canonical URL should not exist

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the page should be indexable" fails on a noindex page
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_noindex.html"
      Then the page should be indexable
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page is not indexable: a "noindex" directive is present in the robots meta tag or the "X-Robots-Tag" header.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the page should not be indexable" fails on an indexable page
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_seo.html"
      Then the page should not be indexable
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page is indexable, but it should not be: no "noindex" directive found in the robots meta tag or the "X-Robots-Tag" header.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the robots meta tag should contain" fails when the directive is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_seo.html"
      Then the robots meta tag should contain "noindex"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The robots meta tag does not contain the "noindex" directive. Found: index, follow.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the robots meta tag should not contain" fails when the directive is present
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_noindex.html"
      Then the robots meta tag should not contain "noindex"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The robots meta tag contains the "noindex" directive, but it should not.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails with no alternates
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No hreflang alternate links were found on the page.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails on an invalid language code
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_hreflang_invalid.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The hreflang value "english" is not a valid language code.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails on an empty href
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_hreflang_emptyhref.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The hreflang alternate for "en" has an empty href.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails without a self-reference
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_hreflang_noself.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No self-referencing hreflang alternate was found for the current URL
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should have reciprocal return links" fails without a return link
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_hreflang_noreturn.html"
      Then the hreflang alternates should have reciprocal return links
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      does not link back to the current URL
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should have reciprocal return links" fails when an alternate is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_hreflang_404.html"
      Then the hreflang alternates should have reciprocal return links
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      returned HTTP status 404
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should have reciprocal return links" fails with no alternates
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the hreflang alternates should have reciprocal return links
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No hreflang alternate links were found on the page.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the Open Graph tags should be valid" fails when tags are missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the Open Graph tags should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Open Graph meta tags are missing or empty: og:type, og:image, og:url.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the Twitter Card tags should be valid" fails when tags are missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the Twitter Card tags should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Twitter Card meta tags are missing or empty: twitter:card, twitter:title, twitter:description.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the following Open Graph tags should exist" fails when a listed tag is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the following Open Graph tags should exist:
        | og:title |
        | og:image |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Open Graph meta tags are missing or empty: og:image.
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then the following Twitter Card tags should exist" fails when a listed tag is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then the following Twitter Card tags should exist:
        | twitter:card |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Twitter Card meta tags are missing or empty: twitter:card.
      """

  @phpserver @jsonld
  Scenario: Assert "Then a :schema_type JSON-LD item should exist" finds the items of every block shape
    When I visit "http://cli:8888/metatags_jsonld.html"
    Then a "PodcastEpisode" JSON-LD item should exist
    And a "WebSite" JSON-LD item should exist
    And a "NewsArticle" JSON-LD item should exist
    And a "Person" JSON-LD item should exist

  @phpserver @jsonld
  Scenario: Assert "Then a :schema_type JSON-LD item should not exist" skips nested items, other vocabularies and absent types
    When I visit "http://cli:8888/metatags_jsonld.html"
    Then a "PodcastSeries" JSON-LD item should not exist
    And a "Event" JSON-LD item should not exist
    And a "Recipe" JSON-LD item should not exist

  @phpserver @jsonld
  Scenario: Assert "Then a :schema_type JSON-LD item with the following properties should exist:" works as expected
    When I visit "http://cli:8888/metatags_jsonld.html"
    Then a "PodcastEpisode" JSON-LD item with the following properties should exist:
      | property            | value                  |
      | url                 | [current_url]          |
      | @id                 | [current_url]          |
      | name                | Episode   one & <more> |
      | duration            | [not_empty]            |
      | partOfSeries        | [not_empty]            |
      | partOfSeries.name   | Café radio             |
      | episodeNumber       | 3                      |
      | isAccessibleForFree | true                   |
      | keywords.1          | testing                |
    And a "NewsArticle" JSON-LD item with the following properties should exist:
      | property | value         |
      | headline | Breaking news |

  @phpserver @jsonld
  Scenario: Assert "Then a :schema_type JSON-LD item with the following properties should exist:" matches any item of the type
    When I visit "http://cli:8888/metatags_jsonld.html"
    Then a "Person" JSON-LD item with the following properties should exist:
      | property | value |
      | name     | John  |

  @test-trait:MetatagTrait
  Scenario: Assert that "Then a :schema_type JSON-LD item should exist" fails when no item has the type
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_jsonld.html"
      Then a "Recipe" JSON-LD item should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No schema.org JSON-LD item of the type "Recipe" was found on the page. Found types: "PodcastEpisode", "WebSite", "Article", "NewsArticle", "Person".
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then a :schema_type JSON-LD item should exist" fails on a page without JSON-LD
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags.html"
      Then a "WebSite" JSON-LD item should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No schema.org JSON-LD item of the type "WebSite" was found on the page. Found types: (none).
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then a :schema_type JSON-LD item should not exist" fails when an item has the type
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_jsonld.html"
      Then a "WebSite" JSON-LD item should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The schema.org JSON-LD item of the type "WebSite" exists on the page, but it should not.
      """

  @test-trait:MetatagTrait
  Scenario Outline: Assert that "Then a :schema_type JSON-LD item should exist" fails on a malformed JSON-LD block
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/<page>"
      Then a "WebSite" JSON-LD item should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      <message>
      """
    Examples:
      | page                         | message                                                                       |
      | metatags_jsonld_invalid.html | The JSON-LD script at the position "2" is not valid JSON: Syntax error.       |
      | metatags_jsonld_scalar.html  | The JSON-LD script at the position "1" holds no JSON object or list.          |

  @test-trait:MetatagTrait
  Scenario Outline: Assert that "Then a :schema_type JSON-LD item with the following properties should exist:" fails when a property does not match
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_jsonld.html"
      Then a "PodcastEpisode" JSON-LD item with the following properties should exist:
        | property   | value      |
        | <property> | <expected> |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      <message>
      """
    Examples:
      | property          | expected      | message                                                                                                           |
      | transcript        | [not_empty]   | - Item 1: the property "transcript" does not exist.                                                              |
      | name.first        | Episode       | - Item 1: the property "name.first" does not exist.                                                              |
      | keywords.5        | behat         | - Item 1: the property "keywords.5" does not exist.                                                              |
      | description       | [not_empty]   | - Item 1: the property "description" is empty, but it should not be.                                             |
      | duration          | PT1H          | - Item 1: the property "duration" has the value "PT42M", but it should have the value "PT1H".                    |
      | partOfSeries      | Café radio    | - Item 1: the property "partOfSeries" holds an object or a list, but it should have the value "Café radio".      |
      | partOfSeries.name | [current_url] | - Item 1: the property "partOfSeries.name" has the value "Café radio", but it should have the value "http://cli:8888/metatags_jsonld.html". |

  @test-trait:MetatagTrait
  Scenario: Assert that "Then a :schema_type JSON-LD item with the following properties should exist:" lists the mismatches of every item
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_jsonld.html"
      Then a "Person" JSON-LD item with the following properties should exist:
        | property | value |
        | name     | Joan  |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No schema.org JSON-LD item of the type "Person" has the expected properties:
      """
    And the output should contain:
      """
      - Item 1: the property "name" has the value "Jane", but it should have the value "Joan".
      """
    And the output should contain:
      """
      - Item 2: the property "name" has the value "John", but it should have the value "Joan".
      """

  @test-trait:MetatagTrait
  Scenario: Assert that "Then a :schema_type JSON-LD item with the following properties should exist:" fails when no item has the type
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_jsonld.html"
      Then a "Recipe" JSON-LD item with the following properties should exist:
        | property | value       |
        | name     | [not_empty] |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No schema.org JSON-LD item of the type "Recipe" was found on the page.
      """

  @test-trait:MetatagTrait
  Scenario Outline: Assert that "Then a :schema_type JSON-LD item with the following properties should exist:" fails on a malformed properties table
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_jsonld.html"
      Then a "WebSite" JSON-LD item with the following properties should exist:
        | <header> | value        |
        | <first>  | Example site |
        | <second> | Example site |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      <message>
      """
    Examples:
      | header   | first | second | message                                                               |
      | name     | name  | url    | The properties table must contain "property" and "value" columns.     |
      | property |       | name   | The properties table has a row with an empty property.                |
      | property | name  | name   | The property "name" appears more than once in the properties table.   |

  @test-trait:MetatagTrait
  Scenario: Assert that "Then a :schema_type JSON-LD item with the following properties should exist:" fails on a properties table without rows
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/metatags_jsonld.html"
      Then a "WebSite" JSON-LD item with the following properties should exist:
        | property | value |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The properties table must list at least 1 property.
      """
