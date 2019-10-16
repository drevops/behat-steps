<?php

namespace IntegratedExperts\BehatSteps\D8;

/**
 * Trait MediaTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait MediaTrait {

  /**
   * Remove media type.
   *
   * @code
   * @Given no "video" media type
   * @endcode
   *
   * @Given no :type media type
   */
  public function mediaRemoveType($type) {
    $type_entity = \Drupal::entityManager()->getStorage('media_type')->load($type);
    if ($type_entity) {
      $type_entity->delete();
    }
  }

}
