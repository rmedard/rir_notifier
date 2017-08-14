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
use Mailchimp\Mailchimp;
use Mailchimp\MailchimpLists;


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

    $createInterestPath = '/lists/{list_id}/interest-categories/{interest_category_id}/interests';

    $mailChimpAPIKey = 'e29c8cf2c4d114d83629a9aee4430992-us16';
    $mailchimp = new Mailchimp($mailChimpAPIKey);
    $mailchimpLists = new MailchimpLists($mailChimpAPIKey);

    $mailChimpListId = '6ec516829b';
    $mailchimpCategoryID = '970f627ce7';

    if (isset($mailchimp)){

      $detailsRequestInterests = Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('type', 'details_request_category')
        ->condition('field_dr_reference', $data->reference)
        ->execute();

      $interestId = NULL;
      if (empty($detailsRequestInterests)){

        $responseData = $mailchimp->request('POST', $createInterestPath, array('list_id' => $mailChimpListId, 'interest_category_id' => $mailchimpCategoryID), array('name' => $data->reference), FALSE, TRUE);
        $detailsRequestCategory = Node::create([
          'type' => 'details_request_category',
          'title' => $data->reference,
          'field_mailchimp_list_id' => $responseData['list_id'],
          'field_mailchimp_category_id' => $responseData['category_id'],
          'field_mailchimp_interest_id' => $responseData['id'],
          'field_dr_reference' => $responseData['name']
        ]);
        $detailsRequestCategory->save();
        $interestId = $responseData['id'];
      } else {
        Drupal::logger('rir_notifier')->alert('Dore: ' . $detailsRequestInterests);
        $detailsRequestCategory = Node::load($detailsRequestInterests[0]);
        $interestId = $detailsRequestCategory->get('field_mailchimp_interest_id')->value;
      }
      $mailchimpLists->addMember($mailChimpListId, $data->email, array('status' => 'subscribed' , 'email_type' => 'html', 'interests' => array($interestId => TRUE)), FALSE);
      Drupal::logger('rir_notifier')->notice('Member suscribed search: ' . $data->email);
    } else {
      Drupal::logger('rir_notifier')->error('Mailchimp Instantiation Failed with Key: ' .$mailChimpAPIKey);
    }

  }
}