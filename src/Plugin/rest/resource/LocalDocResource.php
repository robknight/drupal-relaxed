<?php

namespace Drupal\relaxed\Plugin\rest\resource;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @RestResource(
 *   id = "relaxed:local:doc",
 *   label = "Local document",
 *   serialization_class = {
 *     "canonical" = "Drupal\Core\Entity\ContentEntityInterface",
 *   },
 *   uri_paths = {
 *     "canonical" = "/{db}/_local/{docid}",
 *   }
 * )
 */
class LocalDocResource extends DocResource {

  /**
   * @param string | \Drupal\Core\Config\Entity\ConfigEntityInterface $workspace
   * @param string | \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function head($workspace, $entities) {
    // We know there can only be one entity with HEAD requests.
    $entity = reset($entities);

    if ($entity instanceof ContentEntityInterface && $entity->_local->value == FALSE) {
      throw new NotFoundHttpException();
    }
    return parent::head($workspace, $entities);
  }

  /**
   * @param string | \Drupal\Core\Config\Entity\ConfigEntityInterface $workspace
   * @param string | \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function get($workspace, $entities) {
    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface && $entity->_local->value == FALSE) {
        throw new NotFoundHttpException();
      }
    }
    return parent::get($workspace, $entities);
  }

  /**
   * @param string | \Drupal\Core\Entity\ContentEntityInterface $existing_entity
   * @param \Drupal\Core\Entity\ContentEntityInterface $received_entity
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function put($workspace, $existing_entity, ContentEntityInterface $received_entity = NULL) {
    if ($received_entity instanceof ContentEntityInterface) {
      if (isset($received_entity->_local->value) && $received_entity->_local->value == FALSE) {
        throw new BadRequestHttpException(t('The _local field value can not be set to FALSE when using this endpoint.'));
      }
      else {
        $received_entity->_local->value = TRUE;
      }
    }
    return parent::put($workspace, $existing_entity, $received_entity);
  }

}
