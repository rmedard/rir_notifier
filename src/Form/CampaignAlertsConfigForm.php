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

class CampaignAlertsConfigForm extends ConfigFormBase {

    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames(): array
    {
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
    public function getFormId(): string
    {
        return 'campaign_alerts_admin_settings';
    }

    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $config = $this->config('rir_notifier.settings');
        $form['crypto_secret_key'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Crypto Secret Key'),
            '#default_value' => $config->get('crypto_secret_key'),
            '#required' => TRUE
        );
        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $this->config('rir_notifier.settings')->set('crypto_secret_key', $values['crypto_secret_key'])->save();
        parent::submitForm($form, $form_state);
    }
}