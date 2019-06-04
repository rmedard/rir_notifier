<?php
/**
 * Created by PhpStorm.
 * User: reberme
 * Date: 29/08/2017
 * Time: 19:56
 */

namespace Drupal\rir_notifier\Service;


use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformSubmissionStorage;
use function strtotime;

class DataAccessor
{

    protected $entityTypeManager;

    /**
     * DataAccessor constructor.
     *
     * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
     */
    public function __construct(EntityTypeManager $entityTypeManager)
    {
        $this->entityTypeManager = $entityTypeManager;
    }


    /**
     * @param $reference string reference
     *
     * @return mixed
     */
    function countAdvertsByReference($reference = NULL)
    {
        $location = NULL;
        $advertType = NULL;
        $propertyType = NULL;
        if (isset($reference)) {
            $keys = explode('-', $reference);
            $location = $keys[0];
            $advertType = $keys[1];
            $propertyType = $keys[2];
        }
        return $this->getQuery($location, $advertType, $propertyType)->count()->execute();
    }

    function getDailyAdverts($location = NULL, $advert = NULL, $property = NULL)
    {
        $adverts_ids = $this->getQuery($location, $advert, $property)->execute();
        $storage = $this->entityTypeManager->getStorage('node');
        return $storage->loadMultiple($adverts_ids);
    }

    /**
     * Get Mailchimp API Key from Mailchimp module configuration
     * @return array|mixed|null
     */
    function getMailchimpAPIKey()
    {
        return Drupal::config('mailchimp.settings')->get('api_key');
    }

    private function getQuery($location, $advert, $property)
    {
        $start_time = strtotime('-1 days 00:00:00');
        $end_time = strtotime('-1 days 23:59:59');
        $query = NULL;
        try {
            $storage = $this->entityTypeManager->getStorage('node');
            $query = $storage->getQuery()
                ->condition('type', 'advert')
                ->condition('status', Node::PUBLISHED)
                ->condition('created', array($start_time, $end_time), 'BETWEEN');

            if (isset($location) and !empty($location) and $location !== 'loc') {
                $group = $query->orConditionGroup()
                    ->condition('field_advert_district.entity.name', $location)
                    ->condition('field_advert_sector', $location)
                    ->condition('field_advert_village', $location);
                $query->condition($group);
            }

            if (isset($advert) and !empty($advert) and $advert !== 'ad') {
                $query->condition('field_advert_type', $advert);
            }

            if (isset($property) and !empty($property) and $property !== 'pro') {
                $query->condition('field_advert_property_type', $property);
            }
            return $query;
        } catch (InvalidPluginDefinitionException $e) {
            Drupal::logger('rir_notifier')->error("GetQuery failed: " . $e->getMessage());
            return $query;
        } catch (PluginNotFoundException $e) {
            Drupal::logger('rir_notifier')->error("GetQuery failed: " . $e->getMessage());
            return $query;
        }
    }

    public function getExpiringAdvertsByDate($date)
    {
        try {
            $storage = $this->entityTypeManager->getStorage('node');
            $query = $storage->getQuery()
                ->condition('type', 'advert')
                ->condition('status', Node::PUBLISHED)
                ->condition('field_advert_expirydate', $date, '=');
            $expiring_adverts_ids = $query->execute();
            if (isset($expiring_adverts_ids) and count($expiring_adverts_ids) > 0) {
                return $storage->loadMultiple($expiring_adverts_ids);
            } else {
                Drupal::logger('rir_notifier')->debug('No expiring adverts on date: ' . $date);
                return array();
            }
        } catch (InvalidPluginDefinitionException $e) {
            Drupal::logger('rir_notifier')
                ->error('Runtime error code: ' . $e->getCode() . '. Error message: ' . $e->getMessage());
            return array();
        }
    }


    public function getNotificationSubscribers() {
        try {
            $submissionsStorage = $this->entityTypeManager->getStorage('webform_submission');
            $webform = Webform::load('notification_subscription');
            if ($submissionsStorage instanceof WebformSubmissionStorage) {

                $start_time = strtotime('-150 days 00:00:00');
                $end_time = strtotime('-1 days 23:59:59');
                $nodeStorage = $this->entityTypeManager->getStorage('node');

                $subscribers = array();
                foreach ($submissionsStorage->loadByEntities($webform, null, null) as $sid => $submission) {
                    if ($submission instanceof WebformSubmissionInterface) {

                        $advertType = $submission->getElementData('notif_advert_type');
                        $propertyType = $submission->getElementData('notif_property_type');
                        $location = $submission->getElementData('property_location');

                        if (!isset($advertType) || strtolower($advertType) === 'all') {
                            $advertType = null;
                        }

                        if (!isset($propertyType) || strtolower($propertyType) === 'all') {
                            $propertyType = null;
                        }

                        if (!isset($location) || strtolower($location) === '0') {
                            $location = null;
                        }

                        $query = $nodeStorage->getQuery()
                            ->condition('type', 'advert')
                            ->condition('status', Node::PUBLISHED)
                            ->condition('created', array($start_time, $end_time), 'BETWEEN');

                        if (isset($advertType)) {
                            $query->condition('field_advert_type', $advertType);
                        }

                        if (isset($propertyType)) {
                            $query->condition('field_advert_property_type', $propertyType);
                        }

                        if (isset($location)) {
                            $term = Term::load(intval($location));
                            $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
                            if ($termStorage instanceof TermStorageInterface) {
                                $locations = array();
                                foreach ($termStorage->loadChildren($term->id()) as $locationTerm) {
                                    array_push($locations, $locationTerm->id());
                                }
                                $query->condition('field_advert_district.target_id', $locations, 'IN');
                            }
                        }

                        $advertIds = $query->execute();

                        if (isset($advertIds) and !empty($advertIds)) {
                            $subscribers[$sid] = $advertIds;
                        }
                    }
                }
                return $subscribers;
            }
        } catch (InvalidPluginDefinitionException $e) {
            Drupal::logger('rir_notifier')
                ->error('Invalid plugin definition: ' . $e->getCode() . '. Error message: ' . $e->getMessage());
        } catch (PluginNotFoundException $e) {
            Drupal::logger('rir_notifier')
                ->error('Plugin not found: ' . $e->getCode() . '. Error message: ' . $e->getMessage());
        }
        return array();
    }
}