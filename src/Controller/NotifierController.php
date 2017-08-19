<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 03.08.17
 * Time: 16:03
 */

namespace Drupal\rir_notifier\Controller;


use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\Webform;

class NotifierController extends ControllerBase {

  public function openSubscriptionModal(){
      $query = \Drupal::request()->query;

      $advertType = $query->get('advert');
      $propertyLocation = $query->get('property_location');
      $propertyType = $query->get('property_type');

      $values = [
        'webform_id' => 'notification_subscription',
        'data' => [
          'notif_advert_type' => $advertType,
          'notif_property_location' => $propertyLocation,
          'notif_property_type' => $propertyType
        ]
      ];

      $response = new AjaxResponse();
      $webform = Webform::load($values['webform_id'])->getSubmissionForm($values);
      $response->addCommand(new OpenModalDialogCommand($this->t('Free Email Alert'), $webform, ['width'=> '80%']));
      return $response;
  }
}