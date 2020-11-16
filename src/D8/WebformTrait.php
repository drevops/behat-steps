<?php

namespace IntegratedExperts\BehatSteps\D8;

/**
 * Trait WebformTrait.
 */
trait WebformTrait {

  /**
   * @Given no webform :title
   */
  public function webformDelete($title) {
    $templates = $this->webformLoadMultiple(['title' => $title]);
    foreach ($templates as $template) {
      $template->delete();
    }
  }

  /**
   * @Given I clone webform template :template into :title
   * @Given a webform :title from template :template
   */
  public function webformCloneTemplate($template, $title) {
    $templates = $this->webformGetTemplates(['title' => $template]);

    if (empty($templates)) {
      throw new \RuntimeException(sprintf('Unable to find webform template with title "%s"', $template));
    }
    $template = reset($templates);

    $id = sprintf('%s_%s', $template->get('id'), rand(1000, 10000));
    $description = sprintf('Test %s exercise', $template->get('title'));

    /** @var \Drupal\webform\WebformInterface $clone */
    $clone = $template->createDuplicate();
    $clone->set('title', $title);
    $clone->set('id', $id);
    $clone->set('description', $description);
    $clone->set('template', FALSE);
    $clone->save();

    return $clone->id();
  }

  /**
   * Get webforms templates.
   *
   * @param array $conditions
   *   (optinal) Array of conditions keyed by field names.
   *
   * @return \Drupal\webform\WebformInterface[]
   *   Array of webform template entities.
   */
  protected function webformGetTemplates(array $conditions = []) {
    $defaults = [
      'template' => TRUE,
    ];

    return $this->webformLoadMultiple($defaults + $conditions);
  }

  /**
   * Get webforms.
   *
   * @param array $conditions
   *   (optinal) Array of conditions keyed by field names.
   *
   * @return \Drupal\webform\WebformInterface[]
   *   Array of webform entitites.
   */
  protected function webformLoadMultiple(array $conditions = []) {
    $defaults = [
      'archive' => FALSE,
    ];

    $conditions += $defaults;

    $storage = \Drupal::entityTypeManager()->getStorage('webform');
    $query = $storage->getQuery();

    foreach ($conditions as $name => $value) {
      $query->condition($name, $value, is_string($value) ? 'CONTAINS' : '=');
    }

    $query->sort('title');

    $entity_ids = $query->execute();
    if (empty($entity_ids)) {
      return [];
    }

    /** @var \Drupal\webform\WebformInterface[] $entities */
    $entities = $storage->loadMultiple($entity_ids);

    return $entities;
  }

}
