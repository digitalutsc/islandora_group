<?php

// phpcs:disable Drupal.Commenting.FileComment.NamespaceNoFileDoc
// @todo Re-enable and fix Drupal Coding Standards completely when refactoring.
// phpcs:disable

namespace Drupal\islandora_group;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityForm;
use Drupal\node\NodeInterface;
use Drupal\group\Entity\GroupRelationship;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\Entity\Media;

/**
 * Helper functions.
 */
class Utilities {

  const CONFIG_NAME = 'islandora_group.config';
  const CONFIG_TAXONOMY_VOCAL = 'islandora_group.taxonomy.vocabulary';

  /**
   * @param \Drupal\node\NodeInterface $node
   * @return void
   */
  public static function getMedia(NodeInterface $node) {
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $node->bundle());
    $medias = [];
    foreach ($fields as $fname => $field) {
      if ($field->getType() === "entity_reference"
        && ($field->getSettings()['handler'] === "default:media")) {
        $targets = array_keys($field->getSettings()['handler_settings']['target_bundles']);
        if (is_array($targets) && count($targets) > 0) {
          $media_targets = $node->get($field->getName())->getValue();
          foreach ($media_targets as $mt) {
            if (!empty($mt['target_id'])) {
              array_push($medias, Media::load($mt['target_id']));
            }
          }
        }
      }
    }
    return $medias;
  }

  /**
   * Clear out existing Group-entity relations.
   *
   * @param $entity
   *
   * @return void
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function clear_group_relation_by_entity($entity) {
    // Get access control field from config.
    if ($entity->getEntityTypeId() === "node") {
      // Get access control field from config.
      $access_control_field = self::getAccessControlFieldinNode($entity);
    }
    elseif ($entity->getEntityTypeId() === "media") {
      $access_control_field = self::getAccessControlFieldinMedia($entity);
    }

    // Check if $access_control_field exists and valid.
    if (empty($access_control_field) || !$entity->hasField($access_control_field)) {
      return;
    }
    // For each term, loop through groups-entity.
    foreach (GroupRelationship::loadByEntity($entity) as $group_content) {
      $group_content->delete();
    }
  }

  /**
   * @param \Drupal\node\NodeInterface $node
   *
   * @return mixed
   */
  public static function getAccessControlFieldinNode(NodeInterface $node) {
    $config = \Drupal::config(self::CONFIG_NAME);
    $fields = $config->get("node-type-access-fields");
    return (isset($fields) && array_key_exists($node->bundle(), $fields)) ? $fields[$node->bundle()] : NULL;
  }

  /**
   * @param \Drupal\media\MediaInterface $media
   *
   * @return mixed
   */
  public static function getAccessControlFieldinMedia(MediaInterface $media) {
    $config = \Drupal::config(self::CONFIG_NAME);
    $fields = $config->get("media-type-access-fields");
    return (isset($fields) && array_key_exists($media->bundle(), $fields)) ? $fields[$media->bundle()] : NULL;
  }

  /**
   * @param $nid
   * @param $selected_groups
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function taggingFieldAccessTermsNode($nid, $targets) {
    // Get the node.
    $node = Node::load($nid);

    // Clear any previous relations before adding new ones if called from the UI.
    // 1. Clear field_access_terms in media level.
    self::untag_existed_field_access_terms($node);

    // 2. Clearing group relation with islandora object.
    self::clear_group_relation_by_entity($node);

    if (count($targets) > 0) {
      // Get access control field from config.
      $access_control_field = self::getAccessControlFieldinNode($node);

      if (!empty($access_control_field)) {
        $node->set($access_control_field, $targets);
        $node->save();
      }
    }
    // Add this node to group.
    self::adding_islandora_object_to_group($node);
  }

  /**
   * @param $media_id
   * @param $targets
   * @return void
   */
  public static function taggingFieldAccessTermMedia($media, $targets) {

    self::untag_existed_field_access_terms($media);

    if (count($targets) > 0) {
      // Get access control field from config.
      $access_control_field = self::getAccessControlFieldinMedia($media);
      if (!empty($access_control_field) && $media->hasField($access_control_field)) {
        $media->set($access_control_field, $targets);
        $media->save();
      }
    }
    self::adding_media_only_into_group($media);
  }

  /**
   * Get Islandora Access terms associated with Groups.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getIslandoraAccessTerms() {
    // Create the taxonomy term which has the same name as Group Name.
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree("islandora_access");
    $groups = self::arrange_group_by_name();

    $result = [];
    foreach ($terms as $term) {
      if (in_array($term->name, array_keys($groups))) {
        $group = $groups[$term->name];
        $result[$term->tid] = $term->name . "  <a href='/group/" . $group->id() . "/permissions' target='_blank'>Configure permissions</a>";
      }
    }
    return $result;
  }

  /**
   * Get Islandora Access terms associated with Groups.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getIslandoraAccessTermsinTable() {
    // Create the taxonomy term which has the same name as Group Name.
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree("islandora_access");
    $groups = self::arrange_group_by_name();
    $group_members = self::getGroupMembers();
    $result = [];
    foreach ($terms as $term) {
      if (in_array($term->name, array_keys($groups))) {
        $group = $groups[$term->name];
        $result[$term->tid] = [
          'group_name' => $term->name,
          "group_permission" => t("<a href='/group/" . $group->id() . "/permissions' target='_blank'>Configuration</a>"),
          'group_member' => t($group_members[$group->id()]),
        ];
      }
    }
    return $result;
  }

  /**
   * Create a taxonomy term which is the same name with Group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function sync_associated_taxonomy_with_group(EntityInterface $entity, string $action) {
    if ($entity->getEntityTypeId() !== "group") {
      return;
    }
    $group_type = $entity->bundle();

    // Get the Group associated taxonomy vocabulary.
    $config = \Drupal::config(self::CONFIG_NAME);
    $taxonomy = $config->get($group_type);

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($taxonomy);

    // Create an taxonomy term which has the same name as group name.
    $existedTerm = NULL;
    foreach ($terms as $term) {
      if ($term->name === $entity->label()) {
        $existedTerm = $term;
        break;
      }
    }
    switch ($action) {
      case "insert":
      case "update":
        // If no found terms, create new one.
        if ($existedTerm == NULL) {
          Term::create([
            'name' => $entity->label(),
            'vid' => $taxonomy,
          ])->save();
        }
        break;

      case "delete":
        if ($existedTerm != NULL) {
          $controller = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
          $tobedeleted = $controller->loadMultiple([$existedTerm->tid]);
          $controller->delete($tobedeleted);
        }
        break;

      default:
        break;
    }
  }

  /**
   * @param $node
   * @return bool
   */
  public static function isCollection($node) {
    if ($node->hasField('field_model')) {
      // Get associated term model.
      $term_id = $node->get("field_model")->getValue()[0]['target_id'];
      $term_name = Term::load($term_id)->get('name')->value;

      // If collection, redirect to the Confirm form with selecting children to tag.
      if ($term_name === "Collection") {
        return TRUE;

      }
    }
    return FALSE;
  }

  /**
   * Adding nodes to group.
   *
   * @param $entity
   *
   * @return void
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function adding_islandora_object_to_group($entity) {
    // Get access control field from config.
    $access_control_field = self::getAccessControlFieldinNode($entity);

    // Exit early if it has no access terms.
    if (empty($access_control_field) || !$entity->hasField($access_control_field)) {
      return;
    }

    // Get the access terms for the node.
    $node_terms = $entity->get($access_control_field)->referencedEntities();
    if (empty($node_terms)) {
      // No term, exist.
      return;
    }

    // Arrange groups keyed by their name so we can look them up later.
    $groups_by_name = self::arrange_group_by_name();

    // If there is terms in field_access_term.
    foreach ($node_terms as $term) {
      if (isset($groups_by_name[$term->label()])) {
        $group = $groups_by_name[$term->label()];
        $group->addRelationship($entity, 'group_node:' . $entity->bundle());
      }
    }
  }

  /**
   *
   */
  public static function updating_islandora_object_to_group($entity) {
    // Get access control field from config.
    $access_control_field = self::getAccessControlFieldinNode($entity);

    // Exit early if it has no access terms.
    if (empty($access_control_field) || !$entity->hasField($access_control_field)) {
      return;
    }

    // Clear out group relations with islandora_object first.
    self::clear_group_relation_by_entity($entity);

    // Get the access terms for the node.
    $node_terms = $entity->get($access_control_field)->referencedEntities();
    if (empty($node_terms)) {
      // No term, exist.
      return;
    }

    // Arrange groups keyed by their name so we can look them up later.
    $groups_by_name = self::arrange_group_by_name();

    // If there is terms in field_access_term.
    foreach ($node_terms as $term) {
      if (isset($groups_by_name[$term->label()])) {
        $group = $groups_by_name[$term->label()];
        $group->addRelationship($entity, 'group_node:' . $entity->bundle());
      }
    }
  }

  /**
   * Tag a media in to Group.
   *
   * @param \Drupal\media\MediaInterface $media
   *
   * @return void
   */
  public static function adding_media_of_islandora_object_to_group($node, $media) {
    // For media is no parted of any islandora_object.
    if (empty($node)) {

      // Clear group relation from media.
      self::clear_group_relation_by_entity($media);

      // Add media to node.
      self::adding_media_only_into_group($media);

      return;
    }

    // Get access control field from config.
    $access_control_field = self::getAccessControlFieldinNode($node);

    // For media is parted of an islandora_object.
    if (empty($access_control_field) || !$node->hasField($access_control_field)) {
      return;
    }

    // Arrange groups keyed by their name so we can look them up later.
    $groups_by_name = self::arrange_group_by_name();

    // Clear group relations with the media first.
    self::clear_group_relation_by_entity($media);

    // Get the access terms for the node.
    $terms = $node->get($access_control_field)->referencedEntities();
    if (empty($terms)) {
      // No term, exit;.
      return;
    }

    // Get access control field from config.
    $access_control_field = self::getAccessControlFieldinMedia($media);

    if (empty($access_control_field) || !$media->hasField($access_control_field)) {
      return;
    }
    $media->set($access_control_field, []);

    // If there is terms, loop through and add media group.
    foreach ($terms as $term) {
      if (isset($groups_by_name[$term->label()])) {
        $group = $groups_by_name[$term->label()];
        $group->addRelationship($media, 'group_media:' . $media->bundle());

        // Tag field_access_term in media.
        $media->field_access_terms[] = ['target_id' => $term->id()];
        $media->save();
      }
    }
  }

  /**
   * Remove term(s) in field_access_terms.
   *
   * @param $ne
   *
   * @return void
   */
  public static function clear_term_in_field_access_terms($ne, $group_name) {
    // Get access control field from config.
    $access_control_field = self::getAccessControlFieldinNode($ne);

    // @todo search if the node->field_access_terms contain group name
    if (empty($access_control_field) || !$ne->hasField($access_control_field)) {
      return;
    }
    // Get the access terms for the node.
    $terms = $ne->get($access_control_field)->referencedEntities();
    $i = 0;

    foreach ($terms as $term) {
      if ($term->label() === $group_name) {
        $ne->get($access_control_field)->removeItem($i);
        $ne->save();
        break;
      }
      $i++;
    }
  }

  /**
   * @param $entity
   * @return void
   */
  public static function untag_existed_field_access_terms($entity) {
    if ($entity->getEntityTypeId() === "node") {
      // Get access control field from config.
      $access_control_field = self::getAccessControlFieldinNode($entity);
    }
    elseif ($entity->getEntityTypeId() === "media") {
      $access_control_field = self::getAccessControlFieldinMedia($entity);
    }

    // Check if $access_control_field exists and valid.
    if (empty($access_control_field) || !$entity->hasField($access_control_field)) {
      return;
    }

    $terms = $entity->get($access_control_field)->referencedEntities();
    if (count($terms) > 0) {
      $entity->set($access_control_field, []);
      $entity->save();
    }
  }

  /**
   *
   */
  public static function getGroupMembers() {
    // Arrange groups keyed by their name so we can look them up later.
    $groups = \Drupal::service('entity_type.manager')->getStorage('group')->loadMultiple();
    $group_members = [];
    foreach ($groups as $group) {
      $members = "";
      foreach ($group->getMembers() as $gm) {
        $user = $gm->getUser();
        $members .= $user->getAccountName() . ", ";
      }
      $members .= '<p><a href="/group/' . $group->id() . '/members" target="_blank">Change</a></p>';
      $group_members[$group->id()] = $members;
    }
    return $group_members;
  }

  /**
   * Tag a media in to Group.
   *
   * @param \Drupal\media\MediaInterface $media
   *
   * @return void
   */
  public static function adding_media_only_into_group(MediaInterface $media) {
    // gGet access control field from config.
    $access_control_field = self::getAccessControlFieldinMedia($media);

    // For standalone media (no parent node).
    if (empty($access_control_field) || !$media->hasField($access_control_field)) {
      return;
    }

    // Get field_access_terms.
    $terms = $media->get($access_control_field)->referencedEntities();
    if (empty($terms)) {
      // No term, exit;.
      return;
    }

    // Arrange groups keyed by their name so we can look them up later.
    $groups_by_name = self::arrange_group_by_name();

    foreach ($terms as $term) {
      if (isset($groups_by_name[$term->label()])) {
        $group = $groups_by_name[$term->label()];
        $group->addRelationship($media, 'group_media:' . $media->bundle());
      }
    }
  }

  /**
   *
   */
  public static function updating_media_only_into_group(MediaInterface $media) {
    // Get access control field from config.
    $access_control_field = self::getAccessControlFieldinMedia($media);

    // For standalone media (no parent node).
    if (empty($access_control_field) || !$media->hasField($access_control_field)) {
      return;
    }

    // Clear group relation with media.
    self::clear_group_relation_by_entity($media);

    // Get field_access_terms.
    $terms = $media->get($access_control_field)->referencedEntities();
    if (empty($terms)) {
      // No term, exit;.
      return;
    }

    // Arrange groups keyed by their name so we can look them up later.
    $groups_by_name = self::arrange_group_by_name();

    foreach ($terms as $term) {
      if (isset($groups_by_name[$term->label()])) {
        $group = $groups_by_name[$term->label()];
        $group->addRelationship($media, 'group_media:' . $media->bundle());
      }
    }
  }

  /**
   * Redirect to confirm form to add Children nodes to groups.
   *
   * @param $form
   * @param $form_state
   * @param $entity
   *
   * @return void
   */
  public static function redirect_adding_childrennode_to_group($form, $form_state, $entity) {
    if ($entity->hasField('field_model')) {
      // Get associated term model.
      $term_id = $entity->get("field_model")->getValue()[0]['target_id'];
      $term_name = Term::load($term_id)->get('name')->value;

      // If collection, redirect to the Confirm form with selecting children to tag.
      if ($term_name === "Collection") {
        // Check if this node is collection, redirect to confirm form.
        $form_state->setRedirect('islandora_group.recursive_apply_accesscontrol', [
          'nid' => $entity->id(),
        ]);
      }
    }
  }

  /**
   * Return arranged array of Groups with names.
   *
   * @return array
   */
  public static function arrange_group_by_name(): array {
    // Arrange groups keyed by their name so we can look them up later.
    $groups = \Drupal::service('entity_type.manager')->getStorage('group')->loadMultiple();
    $groups_by_name = [];
    foreach ($groups as $group) {
      $groups_by_name[$group->label()] = $group;
    }
    return $groups_by_name;
  }

  /**
   * DEBUG: Print log to apache log.
   */
  public static function print_log($thing) {
    error_log(print_r($thing, TRUE), 0);
  }

  /**
   * DEBUG: Print log to webpage.
   */
  public static function logging($thing) {
    echo "<pre>";
    print_r($thing);
    echo "</pre>";
  }

  /**
   * DEBUG: Print log in Recent Log messages.
   */
  public static function drupal_log($msg, $type = "error") {
    switch ($type) {
      case "notice":
        \Drupal::logger(basename(__FILE__, '.module'))->notice($msg);
        break;

      case "log":
        \Drupal::logger(basename(__FILE__, '.module'))->log(RfcLogLevel::NOTICE, $msg);
        break;

      case "warning":
        \Drupal::logger(basename(__FILE__, '.module'))->warning($msg);
        break;

      case "alert":
        \Drupal::logger(basename(__FILE__, '.module'))->alert($msg);
        break;

      case "critical":
        \Drupal::logger(basename(__FILE__, '.module'))->critical($msg);
        break;

      case "debug":
        \Drupal::logger(basename(__FILE__, '.module'))->debug($msg);
        break;

      case "info":
        \Drupal::logger(basename(__FILE__, '.module'))->info($msg);
        break;

      case "emergency":
        \Drupal::logger(basename(__FILE__, '.module'))->emergency($msg);
        break;

      default:
        \Drupal::logger(basename(__FILE__, '.module'))->error($msg);
        break;
    }
  }

  /**
   * Custom function form_alter.
   *
   * @return void
   */
  public function cus_form_alter() {
    if ($form_id === "node_islandora_object_edit_form") {
      // When update node form.
      $form['actions']['submit']['#submit'][] = 'form_submit_update_tagging_node_to_group';
    }
    elseif ($form_id === "node_islandora_object_form") {
      // When insert node form.
      $form['actions']['submit']['#submit'][] = 'form_submit_insert_tagging_node_to_group';
    }
    elseif (str_starts_with($form_id, "media_") && str_ends_with($form_id, "_edit_form")) {
      // When update media form.
      $form['actions']['submit']['#submit'][] = 'form_submit_update_tagging_media_to_group';
    }
    elseif (str_starts_with($form_id, "media_") && str_ends_with($form_id, "_add_form")) {
      // When insert update.
      $form['actions']['submit']['#submit'][] = 'form_submit_insert_tagging_media_to_group';
    }
  }

  /**
   * Form submit insert tagging media to group at /media/{{id}}/add.
   *
   * @param $form
   * @param $form_state
   *
   * @return void
   */
  public static function form_submit_insert_tagging_media_to_group($form, $form_state) {
    // For media has parent node, but has different acess term set.
    /** @var \Drupal\Core\Entity\EntityForm $form_object */
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityForm) {
      $media = $form_object->getEntity();

      // Add media only to group.
      self::adding_media_only_into_group($media);
    }
  }

  /**
   * Form submit update tagging media to groups at /media/{{id}}/edit.
   *
   * @param $form
   * @param $form_state
   *
   * @return void
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function form_submit_update_tagging_media_to_group($form, $form_state) {
    // For media has parent node, but has different acess term set.
    /** @var \Drupal\Core\Entity\EntityForm $form_object */
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityForm) {
      $media = $form_object->getEntity();

      // Add media only to group.
      self::updating_media_only_into_group($media);
    }
  }

  /**
   * @param $form
   * @param $form_state
   * @return void
   */
  public static function form_submit_delete_relation_untagging_entity_to_group($form, $form_state) {
    /** @var \Drupal\Core\Entity\EntityForm $form_object */
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityForm) {
      $entity = $form_object->getEntity();
      if ($entity->getEntityTypeId() === 'group_content') {
        $group_content = $entity;
        $group = $group_content->getGroup();
        if ($entity->getEntity()->getEntityTypeId() === "node") {
          $node = $group_content->getEntity();

          // Update field access terms in node level.
          self::clear_term_in_field_access_terms($node, $group->label());
        }
        elseif ($entity->getEntity()->getEntityTypeId() === "media") {
          $media = $group_content->getEntity();

          // Update field access terms in media level.
          self::clear_term_in_field_access_terms($media, $group->label());
        }
      }
    }
  }

  /**
   * Override form submit when tagging node to group when insert at /node/add.
   *
   * @param $form
   * @param $form_state
   *
   * @return void
   */
  public static function form_submit_insert_tagging_node_to_group($form, $form_state) {
    /** @var \Drupal\Core\Entity\EntityForm $form_object */
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityForm) {

      // Get the entity from form.
      $entity = $form_object->getEntity();

      // Add node to group.
      self::adding_islandora_object_to_group($entity);
    }
  }

  /**
   * Override form submit for edit form at /node/nid/edit.
   *
   * @param $form
   * @param $form_state
   *
   * @return void
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function form_submit_update_tagging_node_to_group($form, $form_state) {
    /** @var \Drupal\Core\Entity\EntityForm $form_object */
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityForm) {

      // Get the entity from form.
      $entity = $form_object->getEntity();

      // Add node to group.
      self::updating_islandora_object_to_group($entity);

      // Redirect if the islandora_object is a collection.
      self::redirect_adding_childrennode_to_group($form, $form_state, $entity);
    }

  }

  /**
   * Determine called from Group Module Strage save.
   *
   * @return bool
   */
  public static function isCalledFromGroupModule() {
    $backtrace = debug_backtrace();
    $redudent = FALSE;
    while ($frame = next($backtrace)) {
      if ((isset($frame['class']) && (strpos($frame['class'], 'Drupal\\group') !== FALSE))
        || (isset($frame['class']) && $frame['function'] === 'taggingFieldAccessTermsNode')
        || (isset($frame['class']) && $frame['function'] === 'taggingFieldAccessTermMedia')) {
        $redudent = TRUE;
        break;
      }
    }
    return $redudent;
  }

  /**
   * Determine called from ViewsBulkOperationsActionBase.
   *
   * @return bool
   */
  public static function isCalledFromBulkBatch() {
    $backtrace = debug_backtrace();
    $redudent = FALSE;
    while ($frame = next($backtrace)) {
      if (isset($frame['class']) === TRUE && $frame['class'] === "Drupal\\views_bulk_operations\\Action\\ViewsBulkOperationsActionBase"
        && $frame['function'] === "executeMultiple") {
        $redudent = TRUE;
        break;
      }
    }
    return $redudent;
  }

  /**
   * @param $nid
   * @return array
   */
  public static function getGroupsByNode($nid) {
    $group_ids = [];
    $ids = \Drupal::entityQuery('group_relationship')
      ->condition('entity_id', $nid)
      ->accessCheck(TRUE)
      ->execute();

    $relations = GroupRelationship::loadMultiple($ids);
    foreach ($relations as $rel) {
      if ($rel->getEntity()->getEntityTypeId() == 'node') {
        $group_ids[] = $rel->getGroup()->label();
      }
    }

    return array_unique($group_ids);
  }

  /**
   * @param $nid
   * @return array
   */
  public static function getGroupsByMedia($mid) {
    $group_ids = [];
    $ids = \Drupal::entityQuery('group_relationship')
      ->condition('entity_id', $mid)
      ->accessCheck(TRUE)
      ->execute();

    $relations = GroupRelationship::loadMultiple($ids);
    foreach ($relations as $rel) {
      if ($rel->getEntity()->getEntityTypeId() == 'media') {
        $group_ids[] = $rel->getGroup()->label();
      }
    }
    return array_unique($group_ids);
  }

}
