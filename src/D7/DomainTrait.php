<?php

namespace IntegratedExperts\BehatSteps\D7;

use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeUserCreateScope;

/**
 * Trait DomainTrait.
 *
 * @package IntegratedExperts\BehatSteps\D7
 */
trait DomainTrait {

  /**
   * Assign content to all domains by default.
   *
   * @beforeNodeCreate
   */
  public static function domainBeforeNodeCreateAssignDomains(BeforeNodeCreateScope $scope) {
    $node = $scope->getEntity();
    $all_domains = domain_domains();
    $node->domains = isset($node->domains) && is_string($node->domains)
      ? $node->domains
      : implode(', ', array_keys($all_domains));
    if (strpos($node->domains, ',') !== FALSE && strpos($node->domains, ', ') === FALSE) {
      throw new \RuntimeException('Incorrect use of delimiters to split domains');
    }
    $node->domains = array_unique(explode(', ', $node->domains));
    $node->domains = array_combine(array_values($node->domains), array_values($node->domains));
  }

  /**
   * Assign user to all domains by default.
   *
   * @beforeUserCreate
   */
  public static function domainBeforeUserCreateAssignDomains(BeforeUserCreateScope $scope) {
    $user = $scope->getEntity();
    $all_domains = domain_domains();
    $user->domain_user = isset($user->domain_user)
      ? $user->domain_user
      : array_combine(array_keys($all_domains), array_keys($all_domains));
  }

  /**
   * Visit a path within a domain.
   *
   * @code
   * Given I am on "article" page of "mysubdomain" subdomain
   * When  I go to "article" page of "mysubdomain" subdomain
   * @endcode
   *
   * @Given /^(?:|I )am on "(?P<page>[^"]+)" page of "(?P<subdomain>[^"]+)" subdomain$/
   * @When /^(?:|I )go to "(?P<page>[^"]+)" page of "(?P<subdomain>[^"]+)" subdomain$$/
   */
  public function domainVisitPath($page, $subdomain) {
    if (!domain_machine_name_load($subdomain)) {
      throw new \RuntimeException(sprintf('Invalid subdomain specified %s', $subdomain));
    }

    $parsed = parse_url(rtrim($this->getMinkParameter('base_url'), '/') . '/');
    $parsed['host'] = $subdomain . '.' . $parsed['host'];
    $parsed['path'] = $page;
    $built = $this->domainBuildUrl($parsed);
    $this->visitPath($built);
  }

  /**
   * Visit a homepage on subdomain.
   *
   * @code
   * Given I am on the homepage of "mysubdomain" subdomain
   * When I go to the homepage of "mysubdomain" subdomain
   * @endcode
   *
   * @Given /^(?:|I )am on (?:|the )homepage of "(?P<subdomain>[^"]+)" subdomain$/
   * @When /^(?:|I )go to (?:|the )homepage of "(?P<subdomain>[^"]+)" subdomain$/
   */
  public function domainVisitHomepage($subdomain) {
    $this->domainVisitPath('/', $subdomain);
  }

  /**
   * Helper to build URL from the result of parse_url().
   */
  protected function domainBuildUrl(array $parts) {
    return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
      ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
      (isset($parts['user']) ? "{$parts['user']}" : '') .
      (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
      (isset($parts['user']) ? '@' : '') .
      (isset($parts['host']) ? "{$parts['host']}" : '') .
      (isset($parts['port']) ? ":{$parts['port']}" : '') .
      (isset($parts['path']) ? '/' . ltrim("{$parts['path']}", '/') : '') .
      (isset($parts['query']) ? "?{$parts['query']}" : '') .
      (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
  }

}
