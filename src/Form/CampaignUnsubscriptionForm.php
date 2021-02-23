<?php


namespace Drupal\rir_notifier\Form;


use Drupal;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Class CampaignUnsubscriptionForm
 * @package Drupal\rir_notifier\Form
 */
class CampaignUnsubscriptionForm extends FormBase
{

    protected $submissionId;

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
    public function getFormId(): string
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
    public function buildForm(array $form, FormStateInterface $form_state, $sid = null): array
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
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Unsubscribe'),
            '#button_type' => 'primary',
        );
        $this->submissionId = $sid;
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
    }

    function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);

        $messenger = Drupal::messenger();
        $errorFound = false;
        $email = $form_state->getValue('email');

        $sid = intval($this->submissionId);
        if ($sid != 0) {
            try {
                $submission = WebformSubmission::load($sid);
                if ($submission instanceof WebformSubmissionInterface && $submission->getElementData('notif_email') == $email) {
                    $submission->setElementData('subscription_active', '0');
                    $submission->save();
                    $form_state->setRedirect('<front>');
                    $messenger->addMessage(t('Successfully unsubscribed!'), $messenger::TYPE_STATUS, FALSE);
                } else {
                    $errorFound = true;
                }
            } catch (EntityStorageException $ex) {
                Drupal::logger('rir_notifier')->error('Saving unsubscription failed for sid: ' . $sid);
                $messenger->addMessage(t('Something wrong! Contact administrator.'), $messenger::TYPE_ERROR, FALSE);
            }
        } else {
            $errorFound = true;
        }

        if ($errorFound) {
            $messenger->addMessage(t('Sorry, subscription not found!'), $messenger::TYPE_ERROR, FALSE);
        }
    }
}