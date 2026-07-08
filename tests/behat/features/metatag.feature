Feature: Check that MetatagTrait works
  As Behat Steps library developer
  I want to provide tools to verify metatags on pages
  So that users can test SEO implementation

  @api
  Scenario: Assert that "Then the meta tag should exist with the following attributes:" step works as expected
    When I visit "/"
    Then the meta tag should exist with the following attributes:
      | name    | MobileOptimized |
      | content | width           |

  @trait:MetatagTrait
  Scenario: Assert that negative assertion for "Then the meta tag should exist with the following attributes:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the meta tag should exist with the following attributes:
        | name    | Non_Existing |
        | content | width        |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with specified attributes was not found: {"name":"Non_Existing","content":"width"}
      """

  @api
  Scenario: Assert that "Then the meta tag should not exist with the following attributes:" step works as expected
    When I visit "/"
    Then the meta tag should not exist with the following attributes:
      | name    | Non_Existing |
      | content | width        |

  @trait:MetatagTrait
  Scenario: Assert that negative assertion for "Then the meta tag should not exist with the following attributes:" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      Then the meta tag should not exist with the following attributes:
        | name    | MobileOptimized |
        | content | width           |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with specified attributes should not exist: {"name":"MobileOptimized","content":"width"}
      """

  Scenario: Assert "Then the :metaName meta tag should not contain any HTML tags" works for clean meta tag
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags.html"
    Then the "description" meta tag should not contain any HTML tags

  Scenario: Assert "Then the :metaName meta tag should not contain any HTML tags" works for clean OG meta tag
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags.html"
    Then the "og:title" meta tag should not contain any HTML tags

  @trait:MetatagTrait
  Scenario: Assert that "Then the :metaName meta tag should not contain any HTML tags" fails when meta tag contains HTML
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the "og:description" meta tag should not contain any HTML tags
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "og:description" meta tag contains HTML tags:
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the :metaName meta tag should not contain any HTML tags" fails when meta tag does not exist
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the "nonexistent" meta tag should not contain any HTML tags
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Meta tag with name or property "nonexistent" not found.
      """

  Scenario: Assert canonical URL presence and value
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags_seo.html"
    Then the canonical URL should exist
    And the canonical URL should be "/sites/default/files/metatags_seo.html"

  Scenario: Assert canonical URL absence
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags.html"
    Then the canonical URL should not exist

  Scenario: Assert indexability and robots directives via the robots meta tag
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags_seo.html"
    Then the page should be indexable
    And the meta robots should include "index"
    And the meta robots should not include "noindex"

  Scenario: Assert a non-indexable page via the robots meta tag
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags_noindex.html"
    Then the page should not be indexable
    And the meta robots should include "noindex"

  @api
  Scenario: Assert a non-indexable page via the X-Robots-Tag header
    When I visit "/mysite_core/test-robots-header"
    Then the page should not be indexable

  @api
  Scenario: Assert an indexable page with a non-noindex X-Robots-Tag header
    When I visit "/mysite_core/test-robots-header?value=all"
    Then the page should be indexable

  Scenario: Assert hreflang alternates are valid
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags_seo.html"
    Then the hreflang alternates should be valid

  Scenario: Assert hreflang alternates have reciprocal return links
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags_hreflang_en.html"
    Then the hreflang alternates should be valid
    And the hreflang alternates should have reciprocal return links

  Scenario: Assert Open Graph tags are valid and present
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags_seo.html"
    Then the Open Graph tags should be valid
    And the following Open Graph tags should exist:
      | og:title       |
      | og:description |

  Scenario: Assert Twitter Card tags are valid and present
    Given I am an anonymous user
    When I visit "/sites/default/files/metatags_seo.html"
    Then the Twitter Card tags should be valid
    And the following Twitter Card tags should exist:
      | twitter:image |

  @trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should be" fails on mismatch
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_seo.html"
      Then the canonical URL should be "/wrong-url"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL is "/sites/default/files/metatags_seo.html", but expected "/wrong-url".
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should be" fails when absent
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the canonical URL should be "/some-url"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL is not set.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should exist" fails when absent
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the canonical URL should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL is not set.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the canonical URL should not exist" fails when present
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_seo.html"
      Then the canonical URL should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The canonical URL should not be set, but found "/sites/default/files/metatags_seo.html".
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the page should be indexable" fails on a noindex page
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_noindex.html"
      Then the page should be indexable
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page is not indexable: a "noindex" directive is present in the robots meta tag or the "X-Robots-Tag" header.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the page should not be indexable" fails on an indexable page
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_seo.html"
      Then the page should not be indexable
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page is indexable, but it should not be: no "noindex" directive found in the robots meta tag or the "X-Robots-Tag" header.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the meta robots should include" fails when the directive is missing
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_seo.html"
      Then the meta robots should include "noindex"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The robots meta tag does not include the "noindex" directive. Found: index, follow.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the meta robots should not include" fails when the directive is present
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_noindex.html"
      Then the meta robots should not include "noindex"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The robots meta tag includes the "noindex" directive, but it should not.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails with no alternates
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No hreflang alternate links were found on the page.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails on an invalid language code
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_hreflang_invalid.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The hreflang value "english" is not a valid language code.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails on an empty href
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_hreflang_emptyhref.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The hreflang alternate for "en" has an empty href.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should be valid" fails without a self-reference
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_hreflang_noself.html"
      Then the hreflang alternates should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No self-referencing hreflang alternate was found for the current URL
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should have reciprocal return links" fails without a return link
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_hreflang_noreturn.html"
      Then the hreflang alternates should have reciprocal return links
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      does not link back to the current URL
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should have reciprocal return links" fails when an alternate is missing
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags_hreflang_404.html"
      Then the hreflang alternates should have reciprocal return links
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      returned HTTP status 404
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the hreflang alternates should have reciprocal return links" fails with no alternates
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the hreflang alternates should have reciprocal return links
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No hreflang alternate links were found on the page.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the Open Graph tags should be valid" fails when tags are missing
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the Open Graph tags should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Open Graph meta tags are missing or empty: og:type, og:image, og:url.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the Twitter Card tags should be valid" fails when tags are missing
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the Twitter Card tags should be valid
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Twitter Card meta tags are missing or empty: twitter:card, twitter:title, twitter:description.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the following Open Graph tags should exist" fails when a listed tag is missing
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the following Open Graph tags should exist:
        | og:title |
        | og:image |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Open Graph meta tags are missing or empty: og:image.
      """

  @trait:MetatagTrait
  Scenario: Assert that "Then the following Twitter Card tags should exist" fails when a listed tag is missing
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/metatags.html"
      Then the following Twitter Card tags should exist:
        | twitter:card |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The following required Twitter Card meta tags are missing or empty: twitter:card.
      """
