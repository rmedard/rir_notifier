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

    public function openSubscriptionModal() {
        $query = \Drupal::request()->query;

        $advertType = $query->get('advert');
        $propertyLocation = $query->get('location');
        $propertyType = $query->get('property_type');

        $values = [
          'webform_id' => 'notification_subscription',
          'data' => [
            'notif_advert_type' => $advertType,
            'notif_property_location' => $propertyLocation,
            'notif_property_type' => $propertyType,
          ],
        ];

        $response = new AjaxResponse();
        $webform = Webform::load($values['webform_id'])
          ->getSubmissionForm($values);
        $response->addCommand(new OpenModalDialogCommand($this->t('Free Email Alert'), $webform, ['width' => '80%']));
        return $response;
    }

    public function newsletterPage() {
        $advertType = Drupal::request()->query->get('advert');
        $propertyLocation = Drupal::request()->query->get('location');
        $propertyType = Drupal::request()->query->get('property_type');
        return [
          '#theme' => 'rir_campaign',
            '#adverts' => $this->getDailyAdverts($propertyLocation, $advertType, $propertyType)
        ];
    }

    function getDailyAdverts($location, $advert, $property) {
        return getQuery($location, $advert, $property)->execute();
    }

    function getQuery($location, $advert, $property){
        $query = Drupal::entityQuery('node')
          ->condition('type', 'advert')
          ->condition('status', 1);

        if (isset($location) and !empty($location) and $location !== 'loc'){
            $group = $query->orConditionGroup()
              ->condition('field_advert_district.entity.name', $location)
              ->condition('field_advert_sector', $location)
              ->condition('field_advert_village', $location);
            $query->condition($group);
        }

        if (isset($advert) and !empty($advert) and $advert !== 'ad'){
            $query->condition('field_advert_type', $advert);
        }

        if (isset($property) and !empty($property) and $property !== 'pro'){
            $query->condition('field_advert_property_type', $property);
        }
        return $query;
    }
}