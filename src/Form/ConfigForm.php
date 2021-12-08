<?php

  namespace Drupal\group_access_control\Form;

  use Drupal\Core\Form\ConfigFormBase;
  use Drupal\Core\Form\FormStateInterface;

  /**
   * Class ConfigForm.
   */
  class ConfigForm extends ConfigFormBase
  {

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
      return [
        'group_access_control.config',
      ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
      return 'config_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
      $config = $this->config('group_access_control.config');
      $form = parent::buildForm($form, $form_state);

      $entity = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
      $query = $entity->getQuery();
      $taxonomy_ids = $query->execute();
      $form['taxonomy-vocabulary'] = [
        '#type' => 'select',
        '#name' => 'queues',
        '#title' => $this->t('Select a Taxonomy Vocabulary to associate with Group:'),
        '#required' => TRUE,
        '#options' => $taxonomy_ids,
      ];

      // TODO: Select an toxnomomy vocal, in hook check the caught entity's reference fiedls if any associate with selected vocal, proceed, with Danny hoook

      // TODD: Catch Group created, use this asocated taxno


      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
      parent::submitForm($form, $form_state);

      $this->config('group_access_control.config')
        ->save();
    }

  }
