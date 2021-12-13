<?php

namespace Drupal\group_access_control\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ConfigForm.
 */
class ConfigForm extends ConfigFormBase {
  const CONFIG_NAME = 'group_access_control.config';
  const CONFIG_TAXONOMY_VOCAL = 'group_access_control.taxonomy.vocabulary';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    // get list of existed groups
    $group_types = \Drupal::service('entity_type.manager')->getStorage('group_type')->loadMultiple();

    // get list existing taxonomy vocabulary
    $entity = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
    $query = $entity->getQuery();
    $taxonomy_ids = $query->execute();

    $form['description'] = [
      '#markup' => $this->t("<p>Select a Taxonomy Vocabulary to associate with a Group Type for: </p><ul><li>When a group of that Group Type is created, a term in that vocabulary with the same name as Group name</li></ul>"),
    ];

    foreach ($group_types as $group_type) {
      $form[$group_type->id()] = [
        '#type' => 'select',
        '#name' => $group_type->id(),
        '#title' => $this->t('For <i>' . $group_type->label() . "</i> group:"),
        '#options' => $taxonomy_ids,
        '#required' => true,
        '#default_value' => $config->get($group_type->id(), 0)
      ];
    }


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);

    $groups = \Drupal::service('entity_type.manager')->getStorage('group_type')->loadMultiple();
    foreach ($groups as $group) {
      if ($form_state->getValues()[$group->id()] !== NULL) {
        $config->set($group->id(), $form_state->getValues()[$group->id()])->save();
      }
    }


    parent::submitForm($form, $form_state);
  }

}
