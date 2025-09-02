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
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformSubmissionStorage;
use PDO;
use function strtotime;

class DataAccessor
{

  /**
   * Entity type manager.
   *
   * @var EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  /**
   * DataAccessor constructor.
   *
   * @param EntityTypeManager $entityTypeManager
   */
  public function __construct(EntityTypeManager $entityTypeManager)
  {
    $this->entityTypeManager = $entityTypeManager;
  }


  /**
   * @param $reference string|null reference
   *
   * @return array|int
   */
  function countAdvertsByReference(string $reference = NULL): array|int
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

  function getDailyAdverts($location = NULL, $advert = NULL, $property = NULL): array
  {
    try {
      $adverts_ids = $this->getQuery($location, $advert, $property)->execute();
      $storage = $this->entityTypeManager->getStorage('node');
      return $storage->loadMultiple($adverts_ids);
    } catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      Drupal::logger('rir_notifier')->error("GetQuery failed: " . $e->getMessage());
    }
    return array();
  }

  private function getQuery($location, $advert, $property): ?QueryInterface
  {
    $start_time = strtotime('-1 days 00:00:00');
    $end_time = strtotime('-1 days 23:59:59');
    $query = NULL;
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()->accessCheck()
        ->condition('type', 'advert')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('created', array($start_time, $end_time), 'BETWEEN');

      if (isset($location) and !empty($location) and $location !== 'loc') {
        $group = $query->orConditionGroup()
          ->condition('field_advert_locality_sector', $location)
          ->condition('field_advert_locality_district', $location)
          ->condition('field_advert_locality_province', $location)
          ->condition('field_advert_village', $location);
        $query->condition($group);
      }

      if (isset($advert) and !empty($advert) and $advert !== 'ad') {
        $query->condition('field_advert_type', $advert);
      }

      if (isset($property) and !empty($property) and $property !== 'pro') {
        $query->condition('field_advert_property_type', $property);
      }
    } catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      Drupal::logger('rir_notifier')->error("GetQuery failed: " . $e->getMessage());
    }
    return $query;
  }

  public function getExpiredAdvertIds(): array
  {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck()
      ->condition('type', 'advert')
      ->condition('status', NodeInterface::NOT_PUBLISHED);
    return $query->execute();
  }

  public function getExpiringAdvertsByDate($date): array
  {
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()->accessCheck()
        ->condition('type', 'advert')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('field_advert_expirydate', $date, '=');
      $expiring_adverts_ids = $query->execute();
      if (isset($expiring_adverts_ids) and count($expiring_adverts_ids) > 0) {
        return $storage->loadMultiple($expiring_adverts_ids);
      } else {
        Drupal::logger('rir_notifier')->debug('No expiring adverts on date: ' . $date);
      }
    } catch (InvalidPluginDefinitionException $e) {
      Drupal::logger('rir_notifier')
        ->error('Runtime error code: ' . $e->getCode() . '. Error message: ' . $e->getMessage());
    } catch (PluginNotFoundException $e) {
      Drupal::logger('rir_notifier')
        ->error('Plugin not found: ' . $e->getCode() . '. Error message: ' . $e->getMessage());
    }
    return [];
  }

  public function getComputeCampaigns(): array
  {
    $subscribers = [];
    try {
      $submissionsStorage = $this->entityTypeManager->getStorage('webform_submission');
      $subscriptionWebform = Webform::load('notification_subscription');
      if ($submissionsStorage instanceof WebformSubmissionStorage
        && $subscriptionWebform instanceof WebformInterface
        && $subscriptionWebform->hasSubmissions()) {

        $select = Drupal::service('database')
          ->select('webform_submission_data', 'wsd')
          ->fields('wsd', array('sid'))
          ->orderBy('wsd.sid', 'DESC')
          ->condition('wsd.webform_id', 'notification_subscription', '=')
          ->condition('wsd.name', 'subscription_active', '=')
          ->condition('wsd.value', true, '=')
          ->execute();
        $submissionIds = $select->fetchAll(PDO::FETCH_COLUMN);
        if (isset($submissionIds) && !empty($submissionIds)) {
          $submissions = WebformSubmission::loadMultiple($submissionIds);
          $start_time = strtotime('-1 days 00:00:00'); // Because emails are sent once a day.
          $end_time = strtotime('-1 days 23:59:59');
          $nodeStorage = $this->entityTypeManager->getStorage('node');
          foreach ($submissions as $sid => $submission) {
            if ($submission instanceof WebformSubmissionInterface) {

              $advertType = $submission->getElementData('notif_advert_type');
              $propertyType = $submission->getElementData('notif_property_type');
              $minBedrooms = $submission->getElementData('notif_minimum_bedrooms');
              $maxBedrooms = $submission->getElementData('notif_maximum_bedrooms');
              $currency = $submission->getElementData('notif_currency');
              $minBudget = $submission->getElementData('notif_budget_minimum');
              $maxBudget = $submission->getElementData('notif_budget_maximum');
              $payable = $submission->getElementData('notif_payable');
              $location = $submission->getElementData('property_location');

              $query = $nodeStorage->getQuery()->accessCheck()->range(0, 10)
                ->condition('type', 'advert')
                ->condition('status', NodeInterface::PUBLISHED)
                ->condition('published_at', [$start_time, $end_time], 'BETWEEN');

              if (isset($advertType) && !in_array(strtolower($advertType), ['', 'all'])) {
                $query->condition('field_advert_type', $advertType);
                if ($advertType === 'rent') {
                  if (isset($payable) && $payable !== '') {
                    $query->condition('field_advert_payment', $payable);
                  }
                }
              }

              if (isset($propertyType) && !in_array(strtolower($propertyType), ['', 'all'])) {
                $query->condition('field_advert_property_type', $propertyType);
              }

              if (isset($minBedrooms) && $minBedrooms !== '') {
                $query->condition('field_advert_bedrooms', intval($minBedrooms), '>=');
              }

              if (isset($maxBedrooms) && $maxBedrooms !== '') {
                $query->condition('field_advert_bedrooms', intval($maxBedrooms), '<=');
              }

              if (isset($currency) && $currency !== '') {
                $min = $minBudget;
                $max = $maxBudget;
                if ($currency === 'usd') {
                  $rate = Drupal::service('rir_interface.currency_converter_service')->getUsdRwfRate();
                  if (isset($rate)) {
                    $min = intval($minBudget) * $rate;
                    $max = intval($maxBudget) * $rate;
                  }
                }
                $query->condition('field_advert_price', [$min, $max], 'BETWEEN');
              }

              if (isset($location) && $location !== '0') {
                $term = Term::load(intval($location));
                $location = $term->getName();
                $locationOr = $query->orConditionGroup()
                  ->condition('field_advert_locality_sector', $location)
                  ->condition('field_advert_locality_district', $location)
                  ->condition('field_advert_locality_province', $location);
                $query->condition($locationOr);
              }

              $advertIds = $query->execute();
              if (isset($advertIds) && !empty($advertIds)) {
                $subscribers[$sid] = $advertIds;
              }
            }
          }
        }
      }
    } catch (InvalidPluginDefinitionException $e) {
      Drupal::logger('rir_notifier')
        ->error('Invalid plugin definition: ' . $e->getCode() . '. Error message: ' . $e->getMessage());
    } catch (PluginNotFoundException $e) {
      Drupal::logger('rir_notifier')
        ->error('Plugin not found: ' . $e->getCode() . '. Error message: ' . $e->getMessage());
    }
    return $subscribers;
  }
}
