<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 03.08.17
 * Time: 16:03
 */

namespace Drupal\rir_notifier\Controller;


use Drupal;
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
        $location = Drupal::request()->query->get('location');
        $advert = Drupal::request()->query->get('advert');
        $property = Drupal::request()->query->get('property');
        $dataAccessor = Drupal::service('rir_notifier.data_accessor');
        Drupal::logger('rir_notifier')->debug('Available adverts - controller: ' . 'location: ' . $location . ' advert: ' . $advert . ' property: ' . $property);

        $output = [];
        $output[]['#cache']['max-age'] = 0; // No cache
        $output[] = ['#theme' => 'rir_campaign', '#adverts' => $dataAccessor->getDailyAdverts($location, $advert, $property)];
        return $output;
    }


}