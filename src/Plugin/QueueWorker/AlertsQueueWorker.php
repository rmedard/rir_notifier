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

    const MAILCHIMP_LIST_ID = '6ec516829b';
    const MAILCHIMP_CATEGORY_ID = '2ccf64b283';

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
        $mailChimpAPIKey = $this->getMailchimpAPIKey();
        $mailchimp = new Mailchimp($mailChimpAPIKey);
        $mailchimpLists = new MailchimpLists($mailChimpAPIKey);

        if (isset($mailchimp)) {

            $detailsRequestInterests = Drupal::entityQuery('node')
              ->condition('status', 1)
              ->condition('type', 'details_request_category')
              ->condition('field_dr_reference', $data->reference)
              ->execute();

            if (empty($detailsRequestInterests)) {
                $interestExists = $this->checkIfRemoteInterestExists($data->reference);
                $interest = NULL;
                if ($interestExists !== FALSE){
                    $interest = $interestExists;
                } else {
                    $interest = $mailchimp->request('POST', $createInterestPath, [
                      'list_id' => $this::MAILCHIMP_LIST_ID,
                      'interest_category_id' => $this::MAILCHIMP_CATEGORY_ID,
                    ], ['name' => $data->reference], FALSE, FALSE);
                }

                $segmentExists = $this->checkIfRemoteSegmentExists($data->reference);
                $segment = NULL;
                if ($segmentExists !== FALSE){
                    $segment = $segmentExists;
                } else {
                    $segment = $mailchimpLists->addSegment($this::MAILCHIMP_LIST_ID, $data->reference, ['static_segment' => array($data->email)]);
                }
                $detailsRequestCategory = Node::create([
                  'type' => 'details_request_category',
                  'title' => $data->reference,
                  'field_mailchimp_list_id' => $interest->list_id,
                  'field_mailchimp_category_id' => $interest->category_id,
                  'field_mailchimp_interest_id' => $interest->id,
                  'field_mailchimp_segment_id' => $segment->id,
                  'field_dr_reference' => $interest->name,
                ]);
                $detailsRequestCategory->save();
                $response1 = NULL;
                try {
                    $response1 = $mailchimpLists->addOrUpdateMember($this::MAILCHIMP_LIST_ID, $data->email, [
                      'status' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                      'merge_fields' => [
                        'FNAME' => $data->first_name,
                        'LNAME' => $data->last_name,
                      ],
                      'email_type' => 'html',
                      'interests' => [$interest->id => TRUE],
                    ], FALSE);
                    $mailchimpLists->addSegmentMember($this::MAILCHIMP_LIST_ID, $segment->id, $response1->email_address);
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
                        $response2 = NULL;
                        try {
                          $response2 = $mailchimpLists->addOrUpdateMember($this::MAILCHIMP_LIST_ID, $data->email, [
                            'status' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                            'merge_fields' => [
                              'FNAME' => $data->first_name,
                              'LNAME' => $data->last_name,
                            ],
                            'email_type' => 'html',
                            'interests' => [$interestId => TRUE],
                          ], FALSE);
                          $mailchimpLists->addSegmentMember($this::MAILCHIMP_LIST_ID, $segmentId, $response2->email_address);
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

    private function checkIfRemoteInterestExists($reference){
        $mailchimpLists = new MailchimpLists($this->getMailchimpAPIKey());
        $response = $mailchimpLists->getInterests($this::MAILCHIMP_LIST_ID, $this::MAILCHIMP_CATEGORY_ID);
        foreach ($response->interests as $interest){
            if (strcmp($interest->name, $reference) == 0){
                return $interest;
            }
        }
        return FALSE;
    }

    private function checkIfRemoteSegmentExists($reference){
        $mailchimpLists = new MailchimpLists($this->getMailchimpAPIKey());
        $response = $mailchimpLists->getSegments($this::MAILCHIMP_LIST_ID);
        foreach ($response->segments as $segment){;
            if (strcmp($segment->name, $reference) == 0){
                return $segment;
            }
        }
        return FALSE;
    }

    private function getMailchimpAPIKey(){
        $dataAccessor = Drupal::service('rir_notifier.data_accessor');
        return $dataAccessor->getMailchimpAPIKey();
    }
}