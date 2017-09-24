<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 24.09.17
 * Time: 02:18
 */

namespace Drupal\rir_notifier\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class MailchimpAlertsConfigForm extends ConfigFormBase {

    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames() {
        return [
          'rir_notifier.settings'
        ];
    }

    /**
     * Returns a unique string identifying the form.
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId() {
        return 'mailchimp_alerts_admin_settings';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('rir_notifier.settings');
        $form['main_list_id'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Main list ID'),
            '#default_value' => $config->get('main_list_id'),
            '#required' => TRUE
        );
        $form['main_category_id'] = array(
          '#type' => 'textfield',
          '#title' => $this->t('Main category ID'),
          '#default_value' => $config->get('main_category_id'),
          '#required' => TRUE
        );
        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $this->config('rir_notifier.settings')->set('main_list_id', $values['main_list_id'])->save();
        $this->config('rir_notifier.settings')->set('main_category_id', $values['main_category_id'])->save();
        parent::submitForm($form, $form_state);
    }
}