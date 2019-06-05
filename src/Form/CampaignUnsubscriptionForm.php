<?php


namespace Drupal\rir_notifier\Form;


use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Class CampaignUnsubscriptionForm
 * @package Drupal\rir_notifier\Form
 */
class CampaignUnsubscriptionForm extends FormBase
{

    /**
     * Returns a unique string identifying the form.
     *
     * The returned ID should be a unique string that can be a valid PHP function
     * name, since it's used in hook implementation names such as
     * hook_form_FORM_ID_alter().
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId()
    {
        return 'campaign_unsubscription_form';
    }

    /**
     * Form constructor.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['description'] = [
            '#type' => 'item',
            '#markup' => $this->t('Please enter your email address.'),
        ];
        $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email'),
            '#description' => $this->t('Enter the email address that has to be unsubscribed.'),
            '#required' => TRUE,
        ];
        return $form;
    }

    /**
     * Form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitForm($form, $form_state);
    }

    function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);

        $messenger = Drupal::messenger();
        $errorFound = false;
        $email = $form_state->getValue('email');

        $queryString = Drupal::request()->getQueryString();
        if (isset($queryString)) {
            $params = explode('&', $queryString);
            $sid = intval(explode('=', $params[0])[1]);
            if ($sid != 0) {
                $submission = WebformSubmission::load($sid);
                if ($submission->getElementData('notif_email') == $email) {
                    $submission->setElementData('subscription_active', '0');
                    $submission->save();
                    $form_state->setRedirect('<front>');
                    $messenger->addMessage(t('Successfully unsubscribed!'), $messenger::TYPE_STATUS, FALSE);
                } else {
                    $errorFound = true;
                }
            } else {
                $errorFound = true;
            }
        } else {
            $errorFound = true;
        }

        if ($errorFound) {
            $messenger->addMessage(t('Sorry, subscription not found!'), $messenger::TYPE_ERROR, FALSE);
//        $form_state->setErrorByName('email', t('Sorry, subscription not found!'));
        }
    }
}