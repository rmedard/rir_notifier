<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 10.08.17
 * Time: 17:34
 */

namespace Drupal\rir_notifier\Plugin\QueueWorker;


use Drupal;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use function json_decode;
use function json_encode;
use Mailchimp;

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

//    $code = 'abc123abc123abc123abc123';
//    $client_id =   '679132406599';
//    $client_secret =  'a4a75098d661c74574a825fcc0cd2758797934924362538593';
//    $redirect_url =  'https://www.some-domain.com/callback_file.php';

    $mailChimpAPIKey = 'e29c8cf2c4d114d83629a9aee4430992-us16';
    $mailchimp = new Mailchimp($mailChimpAPIKey);
    $mailChimpListId = '6ec516829b';

    if (isset($mailchimp)){

      $requestCategories = Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('type', 'details_request_category')
        ->condition('field_dr_reference', $data->reference)
        ->execute();

      if (empty($requestCategories)){

        $responseData = $mailchimp->lists($mailChimpListId)->interestCategories()->POST(array('title' => $data->reference, 'type' => 'dropdown'));
        $responseData = json_decode(json_encode($responseData), TRUE);
        $detailsRequestCategory = Node::create([
          'type' => 'details_request_category',
          'title' => $data->reference,
          'field_mailchimp_list_id' => $responseData['list_id'],
          'field_mailchimp_category_id' => $responseData['id'],
          'field_dr_reference' => $responseData['title']
        ]);
        $detailsRequestCategory->save();
        $categoryId = $responseData['id'];
      } else {
        $detailsRequestCategory = Node::load($requestCategories[0]);
        $categoryId = $detailsRequestCategory->get('field_mailchimp_category_id')->value;
      }
      $member = ['email_address' => $data->email, 'status' => 'subscribed', 'email_type' => 'html'];
      $mailchimp->lists($mailChimpListId)->members()->POST($member);
    } else {
      Drupal::logger('rir_notifier')->error('Mailchimp Instantiation Failed with Key: ' .$mailChimpAPIKey);
    }

  }
}