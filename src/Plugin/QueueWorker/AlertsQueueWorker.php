<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 10.08.17
 * Time: 17:34
 */

namespace Drupal\rir_notifier\Plugin\QueueWorker;


use function curl_error;
use function curl_exec;
use function curl_init;
use function curl_setopt_array;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use Drupal;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use function json_decode;
use function json_encode;

/**
 * Class AlertsQueueWorker
 *
 * @package Drupal\rir_notifier\Plugin\QueueWorker
 * @QueueWorker(
 *  id = "alerts_processor",
 *  title = "Alerts custom Queue Worker",
 *  cron = {"time" = 60}
 * )
 */
class AlertsQueueWorker extends QueueWorkerBase {

  /**
   * Works on a single queue item.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   *
   * @throws \Drupal\Core\Queue\RequeueException
   *   Processing is not yet finished. This will allow another process to claim
   *   the item immediately.
   * @throws \Exception
   *   A QueueWorker plugin may throw an exception to indicate there was a
   *   problem. The cron process will log the exception, and leave the item in
   *   the queue to be processed again later.
   * @throws \Drupal\Core\Queue\SuspendQueueException
   *   More specifically, a SuspendQueueException should be thrown when a
   *   QueueWorker plugin is aware that the problem will affect all subsequent
   *   workers of its queue. For example, a callback that makes HTTP requests
   *   may find that the remote server is not responding. The cron process will
   *   behave as with a normal Exception, and in addition will not attempt to
   *   process further items from the current item's queue during the current
   *   cron run.
   *
   * @see \Drupal\Core\Cron::processQueues()
   */
  public function processItem($data) {
    $mailChimpListId = '6ec516829b';
    $mailChimpAPIKey = '32e34053c5d17d18bf833e1c90af369e-us16';
    $requestCategories = Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'details_request_category')
      ->condition('field_dr_reference', $data->reference)
      ->execute();
    if (empty($requestCategories)){
      $url = 'https://us16.api.mailchimp.com/3.0/lists/'.$mailChimpListId.'/interest-categories';
      $ch = curl_init($url);
      curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json',
          'Authorization: Basic ' . $mailChimpAPIKey
        ),
        CURLOPT_POSTFIELDS => json_encode(array('title' => $data->reference, 'type' => 'dropdown'))
      ));
      $response = curl_exec($ch);
      if ($response === FALSE){
        Drupal::logger('rir_notifier')->error(curl_error($ch));
      } else {
        Drupal::logger('rir_notifier')->notice($response);
        $responseData = json_decode($response, TRUE);
        $detailsRequestCategory = Node::create([
          'type' => 'details_request_category',
          'title' => $data->reference,
          'field_mailchimp_list_id' => $responseData['list_id'],
          'field_mailchimp_category_id' => $responseData['id'],
          'field_dr_reference' => $responseData['title']
        ]);
        $detailsRequestCategory->save();
      }
    }

  }
}