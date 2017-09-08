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
use Mailchimp\MailchimpAPIException;
use Mailchimp\MailchimpLists;
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
     *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was
     *   queued.
     *
     * @throws \Drupal\Core\Queue\RequeueException
     *   Processing is not yet finished. This will allow another process to
     *   claim the item immediately.
     * @throws \Exception
     *   A QueueWorker plugin may throw an exception to indicate there was a
     *   problem. The cron process will log the exception, and leave the item
     *   in
     *   the queue to be processed again later.
     * @throws \Drupal\Core\Queue\SuspendQueueException
     *   More specifically, a SuspendQueueException should be thrown when a
     *   QueueWorker plugin is aware that the problem will affect all
     *   subsequent
     *   workers of its queue. For example, a callback that makes HTTP requests
     *   may find that the remote server is not responding. The cron process
     *   will behave as with a normal Exception, and in addition will not
     *   attempt to process further items from the current item's queue during
     *   the current cron run.
     *
     * @see \Drupal\Core\Cron::processQueues()
     */
    public function processItem($data) {

        $createInterestPath = '/lists/{list_id}/interest-categories/{interest_category_id}/interests';
        $dataAccessor = Drupal::service('rir_notifier.data_accessor');
        $mailChimpAPIKey = $dataAccessor->getMailchimpAPIKey();
        $mailchimp = new Mailchimp($mailChimpAPIKey);
        $mailchimpLists = new MailchimpLists($mailChimpAPIKey);

        $mailChimpListId = '6ec516829b';
        $mailchimpCategoryID = '2ccf64b283';

        if (isset($mailchimp)) {

            $detailsRequestInterests = Drupal::entityQuery('node')
              ->condition('status', 1)
              ->condition('type', 'details_request_category')
              ->condition('field_dr_reference', $data->reference)
              ->execute();

            $interestId = NULL;
            if (empty($detailsRequestInterests)) {

                $responseInterest = $mailchimp->request('POST', $createInterestPath, [
                  'list_id' => $mailChimpListId,
                  'interest_category_id' => $mailchimpCategoryID,
                ], ['name' => $data->reference], FALSE, TRUE);

                $responseSegment = $mailchimpLists->addSegment($mailChimpListId, $data->reference);
                $detailsRequestCategory = Node::create([
                  'type' => 'details_request_category',
                  'title' => $data->reference,
                  'field_mailchimp_list_id' => $responseInterest['list_id'],
                  'field_mailchimp_category_id' => $responseInterest['category_id'],
                  'field_mailchimp_interest_id' => $responseInterest['id'],
                  'field_mailchimp_segment_id' => $responseSegment->id,
                  'field_dr_reference' => $responseInterest['name'],
                ]);
                $detailsRequestCategory->save();
                $interestId = $responseInterest['id'];
                try {
                    $response1 = $mailchimpLists->addOrUpdateMember($mailChimpListId, $data->email, [
                      'status' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                      'merge_fields' => [
                        'FNAME' => $data->first_name,
                        'LNAME' => $data->last_name,
                      ],
                      'email_type' => 'html',
                      'interests' => [$interestId => TRUE],
                    ], FALSE);
                    $mailchimpLists->addSegmentMember($mailChimpListId, $responseSegment->id, $data->email);
                    Drupal::logger('rir_notifier')
                      ->notice('New member subscribed: ' . $data->email . ' Response:' . json_encode($response1));
                } catch (MailchimpAPIException $ex) {
                    Drupal::logger('rir_notifier')
                      ->error('MailChimp error: Code: ' . $response1->status . ' Title: ' . $response1->title);
                }

            } else {
                foreach ($detailsRequestInterests as $interestRequest) {
                    $detailsRequestCategory = Node::load($interestRequest);
                    if (isset($detailsRequestCategory)) {
                        $interestId = $detailsRequestCategory->get('field_mailchimp_interest_id')->value;
                        $segmentId = $detailsRequestCategory->get('field_mailchimp_segment_id')->value;
                        try {
                          $response2 = $mailchimpLists->addOrUpdateMember($mailChimpListId, $data->email, [
                            'status' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                            'merge_fields' => [
                              'FNAME' => $data->first_name,
                              'LNAME' => $data->last_name,
                            ],
                            'email_type' => 'html',
                            'interests' => [$interestId => TRUE],
                          ], FALSE);
                          $mailchimpLists->addSegmentMember($mailChimpListId, $segmentId, $data->email);
                          Drupal::logger('rir_notifier')
                            ->notice('Member subscription updated: ' . $data->email . ' Response:' . json_encode($response2));
                        } catch (MailchimpAPIException $ex) {
                          Drupal::logger('rir_notifier')
                            ->error('MailChimp Code: ' . $response2->status . ' Title: ' . $response2->title);
                        }
                    }
                }
            }
        }
        else {
            Drupal::logger('rir_notifier')
              ->error('Mailchimp Instantiation Failed with Key: ' . $mailChimpAPIKey);
        }
    }
}