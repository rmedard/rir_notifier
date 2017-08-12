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
      $districts = explode("-", $query->get('districts'));
      $rooms = $query->get('rooms');
      $propertyType = $query->get('property_type');
      $price = $query->get('price');

      $values = [
        'webform_id' => 'notification_subscription',
        'data' => [
          'notif_advert_type' => $advertType,
          'notif_districts' => $districts,
          'notif_nbr_rooms' => $rooms,
          'notif_property_type' => $propertyType,
          'notif_price' => $price
        ]
      ];

      $response = new AjaxResponse();
      $webform = Webform::load($values['webform_id'])->getSubmissionForm($values);
      $response->addCommand(new OpenModalDialogCommand($this->t('Free Email Alert'), $webform, ['width'=> '80%']));
      return $response;
  }

  public function page(){
    $element = array(
      '#markup' => 'Hello, This is a test page'
    );
    return $element;
  }
}