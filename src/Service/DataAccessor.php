<?php
/**
 * Created by PhpStorm.
 * User: reberme
 * Date: 29/08/2017
 * Time: 19:56
 */

namespace Drupal\rir_notifier\Service;


use Drupal;
use Drupal\node\Entity\Node;
use function strtotime;

class DataAccessor {

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
        return Node::loadMultiple($adverts);
    }

    /**
     * Get Mailchimp API Key from Mailchimp module configuration
     * @return array|mixed|null
     */
    function getMailchimpAPIKey(){
        return Drupal::config('mailchimp.settings')->get('api_key');
    }

    private function getQuery($location, $advert, $property){
        $start_time = strtotime('-2 days 00:00:00');
        $end_time = strtotime('-2 days 23:59:59');
        $query = Drupal::entityQuery('node')
          ->condition('type', 'advert')
          ->condition('status', 1)
          ->condition('created', array($start_time, $end_time), 'BETWEEN');

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