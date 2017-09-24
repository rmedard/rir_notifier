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
        $mailChimpAPIKey = $this->getMailchimpAPIKey();
        $mailchimp = new Mailchimp($mailChimpAPIKey);
        $mailchimpLists = new MailchimpLists($mailChimpAPIKey);

        if (isset($mailchimp)) {

            $detailsRequestInterestsIds = Drupal::entityQuery('node')
              ->condition('status', 1)
              ->condition('type', 'details_request_category')
              ->condition('field_dr_reference', $data->reference)
              ->execute();

            if (isset($detailsRequestInterestsIds) and count($detailsRequestInterestsIds) > 0){
                $detailsRequestInterests = Node::loadMultiple($detailsRequestInterestsIds);
                foreach ($detailsRequestInterests as $detailsRequestInterest){
                    $interestId = $detailsRequestInterest->get('field_mailchimp_interest_id')->value;
                    $segmentId = $detailsRequestInterest->get('field_mailchimp_segment_id')->value;

                    try {
                        $response2 = $mailchimpLists->addOrUpdateMember($this->getMailchimpListId(), $data->email,
                          array(
                            'status' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                            'status_if_new' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                            'merge_fields' => ['FNAME' => $data->first_name, 'LNAME' => $data->last_name],
                            'email_type' => 'html',
                            'interests' => [$interestId => TRUE],
                            ), FALSE);
                        if (isset($response2) and !empty($response2->email_address)){
                            $mailchimpLists->addSegmentMember($this->getMailchimpListId(), $segmentId, $response2->email_address);
                            Drupal::logger('rir_notifier')->notice('Member subscription updated: ' . $data->email . ' Response:' . json_encode($response2));
                        } else {
                            Drupal::logger('rir_notifier')->error('Failed to subscribe: ' . $data->email);
                        }
                    } catch (MailchimpAPIException $ex) {
                        Drupal::logger('rir_notifier')->error('MailChimp Error: ' . $ex);
                    }
                }
            } else {
                $interestExists = $this->checkIfRemoteInterestExists($data->reference);
                $interest = NULL;
                if ($interestExists !== FALSE){
                    $interest = $interestExists;
                } else {
                    $interest = $mailchimp->request('POST', $createInterestPath, [
                      'list_id' => $this->getMailchimpListId(),
                      'interest_category_id' => $this->getMailchimpCategoryId(),
                    ], ['name' => $data->reference], FALSE, FALSE);
                }

                try {

                    $response1 = $mailchimpLists->addOrUpdateMember($this->getMailchimpListId(), $data->email,
                      array(
                        'status' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                        'status_if_new' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                        'merge_fields' => ['FNAME' => $data->first_name, 'LNAME' => $data->last_name],
                        'email_type' => 'html',
                        'interests' => [$interest->id => TRUE],
                      ), FALSE);

                    $segmentExists = $this->checkIfRemoteSegmentExists($data->reference);
                    $segment = NULL;
                    if ($segmentExists !== FALSE){
                        $segment = $segmentExists;
                        $mailchimpLists->addSegmentMember($this->getMailchimpListId(), $segment->id, $response1->email_address);
                    } else {
                        $segment = $mailchimpLists->addSegment($this->getMailchimpListId(),
                          $data->reference,
                          array(
                            'static_segment' => array($response1->email_address)
                          )
                        );
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
                } catch (MailchimpAPIException $ex) {
                    Drupal::logger('rir_notifier')->error('MailChimp error: ' . $ex);
                }

            }
        } else {
            Drupal::logger('rir_notifier')->error('Mailchimp Instantiation Failed with Key: ' . $mailChimpAPIKey);
        }
    }

    private function checkIfRemoteInterestExists($reference){
        $mailchimpLists = new MailchimpLists($this->getMailchimpAPIKey());
        $fields = array('interests.category_id', 'interests.list_id', 'interests.id', 'interests.name');
        $response = $mailchimpLists->getInterests($this->getMailchimpListId(), $this->getMailchimpCategoryId(), ['fields' => $fields]);
        foreach ($response->interests as $interest){
            if (strcmp($interest->name, $reference) == 0){
                return $interest;
            }
        }
        return FALSE;
    }

    private function checkIfRemoteSegmentExists($reference){
        $mailchimpLists = new MailchimpLists($this->getMailchimpAPIKey());
        $fields = array('segments.id', 'segments.name');
        $response = $mailchimpLists->getSegments($this->getMailchimpListId(), array('fields' => $fields));
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

    private function getMailchimpListId(){
        return Drupal::config('rir_notifier.settings')->get('main_list_id');
    }

    private function getMailchimpCategoryId(){
        return Drupal::config('rir_notifier.settings')->get('main_category_id');
    }
}