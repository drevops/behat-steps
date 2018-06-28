<?php

namespace IntegratedExperts\BehatSteps\D8;

/**
 * Trait MediaTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait MediaTrait {

  /**
   * @Given no :type media type
   */
  public function mnediaRemoveType($type) {
    $type_entity = \Drupal::entityManager()->getStorage('media_type')->load($type);
    if ($type_entity) {
      $type_entity->delete();
    }
  }

}
