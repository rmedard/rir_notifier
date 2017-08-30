<?php
/**
 * Created by PhpStorm.
 * User: reberme
 * Date: 29/08/2017
 * Time: 19:56
 */

namespace Drupal\rir_notifier\Service;


use Drupal;

class DataAccessor {

    const MAILCHIMP_API_KEY = 'e29c8cf2c4d114d83629a9aee4430992-us16';

    /**
     * @param $reference string reference
     *
     * @return mixed
     */
    function countAdvertsByReference($reference = NULL) {
        $location = NULL;
        $advertType = NULL;
        $propertyType = NULL;
        if (isset($reference)){
            $keys = explode('-', $reference);
            $location = $keys[0];
            $advertType = $keys[1];
            $propertyType = $keys[2];
        }
        return $this->getQuery($location, $advertType, $propertyType)->count()->execute();
    }

    function getDailyAdverts($location = NULL, $advert = NULL, $property = NULL) {
        $adverts = $this->getQuery($location, $advert, $property)->execute();
        Drupal::logger('rir_notifier')->debug('adverts data: ' . json_encode($adverts, TRUE));
        return $adverts;
    }

    private function getQuery($location, $advert, $property){
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