<?php

namespace Drupal\relaxed\ParamConverter;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\multiversion\Entity\UuidIndex;
use Drupal\multiversion\Entity\RevisionIndex;
use Symfony\Component\Routing\Route;

class DocIdConverter implements ParamConverterInterface {

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * @var \Drupal\multiversion\Entity\UuidIndex
   */
  protected $uuidIndex;

  /**
   * @var string
   */
  protected $key = 'entity_uuid';

  /**
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   */
  public function __construct(EntityManagerInterface $entity_manager, UuidIndex $uuid_index, RevisionIndex $rev_index) {
    $this->entityManager = $entity_manager;
    $this->uuidIndex = $uuid_index;
    $this->revIndex = $rev_index;
  }

  /**
   * Converts a UUID into an existing entity.
   *
   * @return string | \Drupal\Core\Entity\EntityInterface
   *   The entity if it exists in the database or else the original UUID string.
   */
  public function convert($uuid, $definition, $name, array $defaults) {
    $entity_type_id = substr($definition['type'], strlen($this->key . ':'));
    $entity_id = NULL;
    $revision_id = NULL;
    $request = \Drupal::request();

    // Fetch parameters.
    $open_revs_query = trim($request->query->get('open_revs'), '[]');
    if (!$rev_query = $request->query->get('rev')) {
      $rev_query = $request->headers->get('if-none-match');
    }

    // Use the indices to resolve the entity and revision ID.
    if ($rev_query && $item = $this->revIndex->get("$uuid:$rev_query")) {
      $entity_type_id = $item['entity_type'];
      $entity_id = $item['entity_id'];
      $revision_id = $item['revision_id'];
    }
    elseif ($item = $this->uuidIndex->get($uuid)) {
      $entity_type_id = $item['entity_type'];
      $entity_id = $item['entity_id'];
    }

    // Return the plain UUID if we're missing information.
    if (!$entity_id || !$entity_type_id) {
      return $uuid;
    }
    $storage = $this->entityManager->getStorage($entity_type_id);

    if ($open_revs_query) {
      $open_revs = array();
      if ($open_revs_query === 'all') {
        $entity = $storage->load($entity_id);
        // @todo _revs_info doesn't actually denote only open revisions.
        foreach ($entity->_revs_info as $item) {
          $open_revs[] = $item->rev;
        }
      }
      else {
        $open_revs = explode(',', $open_revs_query);
      }
      $revision_ids = array();
      foreach ($open_revs as $open_rev) {
        if ($item = $this->revIndex->get("$uuid:$open_rev")) {
          $revision_ids[] = $item['revision_id'];
        }
      }
      $revisions = array();
      foreach ($revision_ids as $revision_id) {
        $revisions[] = $storage->loadRevision($revision_id);
      }
      return $revisions;
    }
    elseif ($revision_id) {
      return $storage->loadRevision($revision_id) ?: $uuid;
    }
    return $storage->load($entity_id) ?: $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return ($name == 'docid');
  }
}
