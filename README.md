# Behat Steps
Collection of Behat steps for Drupal 8 and Drupal 7 development.

[![CircleCI](https://circleci.com/gh/integratedexperts/behat-steps.svg?style=shield)](https://circleci.com/gh/integratedexperts/behat-steps)
[![Latest Stable Version](https://poser.pugx.org/integratedexperts/behat-steps/v/stable)](https://packagist.org/packages/integratedexperts/behat-steps)
[![Total Downloads](https://poser.pugx.org/integratedexperts/behat-steps/downloads)](https://packagist.org/packages/integratedexperts/behat-steps)
[![License](https://poser.pugx.org/integratedexperts/behat-steps/license)](https://packagist.org/packages/integratedexperts/behat-steps)

# Why traits?
Usually, such packages implement own Drupal driver with several contexts, service containers and a lot of other useful architectural structures.
But for this simple library, using traits helps to lower entry barrier for usage, maintenance and support. 
This package may later be refactored to use proper architecture. 

# Installation
`composer require --dev integratedexperts/behat-steps`

# Usage
In `FeatureContext.php`:

```
<?php

use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\D7\ContentTrait;
use IntegratedExperts\BehatSteps\D7\DomainTrait;
use IntegratedExperts\BehatSteps\D7\EmailTrait;
use IntegratedExperts\BehatSteps\D7\OverrideTrait;
use IntegratedExperts\BehatSteps\D7\ParagraphsTrait;
use IntegratedExperts\BehatSteps\D7\UserTrait;
use IntegratedExperts\BehatSteps\D7\VariableTrait;
use IntegratedExperts\BehatSteps\D7\WatchdogTrait;
use IntegratedExperts\BehatSteps\D8\MediaTrait;
use IntegratedExperts\BehatSteps\D8\UserTrait;
use IntegratedExperts\BehatSteps\DateTrait;
use IntegratedExperts\BehatSteps\Field;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\LinkTrait;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use ContentTrait;
  use DomainTrait;
  use EmailTrait;
  use MediaTrait;
  use OverrideTrait;
  use ParagraphsTrait;
  use PathTrait;
  use UserTrait;
  use VariableTrait;
  use WatchdogTrait;
  use DateTrait;
  use FieldTrait;
  use LinkTrait;
  use PathTrait;
  use ResponseTrait;

}
```

## Exceptions
- `\Exception` is thrown for all assertions.
- `\RuntimeException` is thrown for any unfulfilled requirements within a step. 

## Available steps

### Common

```
FieldTrait.php
    Then I see field :name
    Then I don't see field :name
    Then field :name :exists on the page
    Then field :name is :disabled on the page
    Then field :name should be :presence on the page and have state :state
LinkTrait.php
    Then I should see the link :text with :href
    Then I should see the link :text with :href in :locator
PathTrait.php
    Then I should be in the :path path
    Then I should not be in the :path path
    Then I :can visit :path with HTTP credentials :user :pass
ResponseTrait.php
    Then response contains header :name
    Then response does not contain header :name
    Then response header :name contains :value
    Then response header :name does not contain :value    
JsTrait.php
    When I accept confirmation dialogs
    When I do not accept confirmation dialogs
    When /^(?:|I )click (an?|on) "(?P<element>[^"]*)" element$/
PathTrait.php
    When I visit :path then the final URL should be :alias
```    
   
### Drupal 7

```
ContentTrait.php
    Given /^no ([a-zA-z0-9_-]+) content:$/
    Given no managed files:
    When I visit :type :title
    When I edit :type :title
DomainTrait.php
    Given /^(?:|I )am on "(?P<page>[^"]+)" page of "(?P<subdomain>[^"]+)" subdomain$/
    Given /^(?:|I )am on (?:|the )homepage of "(?P<subdomain>[^"]+)" subdomain$/
    When /^(?:|I )go to "(?P<page>[^"]+)" page of "(?P<subdomain>[^"]+)" subdomain$$/
    When /^(?:|I )go to (?:|the )homepage of "(?P<subdomain>[^"]+)" subdomain$/   
EmailTrait.php
    Given I enable the test email system
    Given I disable the test email system
    When I clear the test email system queue
    When I follow the link number :number in the email with the subject:
    Then an email is sent to :address
    Then no emails were sent
    Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/
    Then an email :field contains:
    Then an email :field contains exact:
    Then an email :field does not contain:
    Then an email :field does not contain exact:
    Then file :name attached to the email with the subject:      
FileTrait.php
    Given managed file:
FileDownloadTrait.php
    Then I download file from :url
    Then I download file from link :link
    Then I see download :link link :presence(on the page)
    Then downloaded file contains:
    Then downloaded file name is :name
    Then downloaded file is zip archive that contains files:
    Then downloaded file is zip archive that does not contain files:   
MediaTrait.php   
    When /^(?:|I )attach the file "(?P<path>[^"]*)" to "(?P<field>(?:[^"]|\\")*)" media field$/
OverrideTrait.php
    Given I am logged in as a user with the :role role(s)
    Given I am logged in as a/an :role
ParagraphsTrait.php
    When :field_name in :node_title node of type :node_type has :paragraph_type paragraph:   
TaxonomyTrait.php
    Given no :vocabulary terms:
    Given taxonomy term :name from vocabulary :vocab exists
    Then :node_title has :field_name field populated with( the following) terms from :vocabulary( vocabulary):
    Then "apple" in "classification" vocabulary has parent "fruit"
    Then "apple" in "classification" vocabulary has parent "fruit" and depth "1"
    Then /^"(?P<term_name>[^"]*)" in "(?P<vocabulary>[^"]*)" vocabulary has parent "(?P<parent_term_name>[^"]*)"( and depth "(?P<depth>[^"]*)")?$/   
UserTrait.php
    Given no users:
    When I visit user :name profile     
    Then user :name has :roles role(s) assigned
    Then user :name does not have :roles role(s) assigned
    Then user :name has :status status   
VariableTrait.php
    Then variable :name has value :value
    Then variable :name has value:
    Then variable :name does not have value :value
    Then variable :name does not have a value      
```    
   
### Drupal 8

```
ContentTrait.php
    Given /^no ([a-zA-z0-9_-]+) content:$/
    Given no managed files:
    When I visit :type :title
    When I edit :type :title
    When the moderation state of :type :title changes from :old_state to :new_state
    When /^I fill in CKEditor on field "([^"]*)" with "([^"]*)"$/   
MediaTrait.php
    Given no "video" media type
    Given no :type media type     
MenuTrait.php
    Given no menus:
    Given no menus:
TaxonomyTrait.php
    Given vocabulary :vid with name :name exists
    Given taxonomy term :name from vocabulary :vocabulary_id exists
    Given no :vocabulary terms:
```    

### Skipping before scenario hooks
Some traits provide beforeScenario hook implementations. These can be disabled
by adding `behat-steps-skip:METHOD_NAME` tag to your test. 

For example, to skip beforeScenario hook from JsTrait, add 
`@behat-steps-skip:jsBeforeScenarioInit` tag to the feature.

## Development

### Local environment setup
- Make sure that you have latest versions of all required software installed:
  - [Docker](https://www.docker.com/)
  - [Pygmy](https://pygmy.readthedocs.io/)
  - [Ahoy](https://github.com/ahoy-cli/ahoy)
- Make sure that all local web development services are shut down (Apache/Nginx, Mysql, MAMP etc).
- Checkout project repository (in one of the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control)).  
- `pygmy up`
- `ahoy build` for Drupal 8 build or `DRUPAL_VERSION=7 ahoy build` for Drupal 7.
- Access built site at http://behat-steps.docker.amazee.io/  

Please note that you will need to rebuild to work on a different Drupal version.

Use `ahoy --help` to see the list of available commands.   

### Behat tests
After every `ahoy build`, a new installation of Drupal is created in `build` directory.
This project uses fixture Drupal sites (sites with pre-defined configuration)
in order to simplify testing (i.e., the test does not create a content type
but rather uses a content type created from configuration during site installation).

- Run all tests: `ahoy test-bdd`
- Run all scenarios in specific feature file: `ahoy test-bdd path/to/file`
- Run all scenarios tagged with `@wip` tag: `ahoy test-bdd -- --tags=wip`
- Tests tagged with `@d7` or `@d8` will be ran for Drupal 7 and Drupal 8 respectively.
- Tests tagged with both `@d7` and `@d8` are agnostic to Drupal version and will run for both versions. 

To debug tests from CLI:
- `ahoy debug`
- Set breakpoint and run tests - your IDE will pickup incoming debug connection.

To update fixtures:
- Make required changes in the installed fixture site
- Run `ahoy drush cex -y` for Drupal 8 or `ahoy drush fua -y` for Drupal 7
- Run `ahoy update-fixtures` for Drupal 8 or `DRUPAL_VERSION=7 ahoy update-fixtures` for Drupal 7 to export configuration changes from build directory to the fixtures directory. 
