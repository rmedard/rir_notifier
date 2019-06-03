<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 09/09/2017
 * Time: 02:25
 */

namespace Drupal\rir_notifier\Plugin\QueueWorker;


use Drupal;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Mailchimp\Mailchimp;
use Mailchimp\MailchimpAPIException;
use Mailchimp\MailchimpCampaigns;
use Mailchimp\MailchimpLists;

/**
 * Class CampaignQueueWorker
 *
 * @package Drupal\rir_notifier\Plugin\QueueWorker
 * @QueueWorker(
 *  id = "campaigns_processor",
 *  title = "Campaigns Queue Worker",
 *  cron = {"time" = 90}
 * )
 */
class CampaignQueueWorker extends QueueWorkerBase {

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

//        $storage = $this->entityTypeManager->getStorage('node');
//        $query = $storage->getQuery()
//            ->condition('type', 'advert')





        Drupal::logger('rir_notifier')->notice('Sending notifications started');
        $dataAccessor = Drupal::service('rir_notifier.data_accessor');
        $APIKey = $dataAccessor->getMailchimpAPIKey();
        $campaigns = new MailchimpCampaigns($APIKey);
        $lists = new MailchimpLists($APIKey);
        $mailchimp = new Mailchimp($APIKey);
        $ids = Drupal::entityQuery('node')
          ->condition('type', 'details_request_category')
          ->execute();
        $interests = Node::loadMultiple($ids);
        foreach ($interests as $interest) {

            $reference = $interest->get('field_dr_reference')->value;
            if ($dataAccessor->countAdvertsByReference($reference) > 0) {
                $list_id = $interest->get('field_mailchimp_list_id')->value;
                $category_id = $interest->get('field_mailchimp_category_id')->value;
                $interest_ids = $interest->get('field_mailchimp_interest_id')->value;
                Drupal::logger('rir_notifier')
                  ->debug('list: ' . $list_id . ' category: ' . $category_id . ' interest: ' . $interest_ids);
                $members = $lists->getMembers($list_id,
                  [
                    'status' => MailchimpLists::MEMBER_STATUS_SUBSCRIBED,
                    'interest_category_id' => $category_id,
                    'interest_ids' => $interest_ids,
                    'interest_match' => 'all',
                  ]
                );
                $members_obj = (object) [
                  'list_id' => $list_id,
                  'segment_opts' => (object) ['saved_segment_id' => intval($interest->get('field_mailchimp_segment_id')->value)],
                ];
                $members_array = json_decode(json_encode($members, TRUE));
                if ($members_array->total_items > 0) {
                    $settings = [
                      'subject_line' => 'New properties matching your selection on Houseinrwanda.com',
                      'title' => 'Campaign - ' . $reference,
                      'from_name' => Drupal::config('system.site')->get('name'),
                      'reply_to' => Drupal::config('system.site')->get('mail'),
                      'inline_css' => TRUE,
                    ];
                    $campaign = NULL;
                    try {
                        $campaign = $campaigns->addCampaign(MailchimpCampaigns::CAMPAIGN_TYPE_REGULAR, $members_obj, (object) $settings);
                        if ($campaign) {
                            $campaign = json_decode(json_encode($campaign, TRUE));
                            Drupal::logger('rir_notifier')
                              ->debug('Created campaign: ' . $campaign->id);
                            try {
                                $obj = $campaigns->setCampaignContent($campaign->id, ['html' => getCampaignHtmlContent($reference)]);
                                if (!empty($obj)) {
                                    $campaigns->send($campaign->id, FALSE);
                                    //$mailchimp->processBatchOperations();
                                }
                                else {
                                    Drupal::logger('rir_notifier')
                                      ->debug('Empty campaign content: ' . $campaign->id);
                                }
                            } catch (MailchimpAPIException $exp1) {
                                Drupal::logger('rir_notifier')
                                  ->error('Sending Campaign: ' . $exp1->getMessage() . ' Full trace: ' . $exp1->getTraceAsString());
                            }
                        }
                    } catch (MailchimpAPIException $exp2) {
                        Drupal::logger('rir_notifier')
                          ->error('Creating Campaign: ' . $exp2->getMessage() . ' Full trace: ' . $exp2->getTraceAsString());
                    }
                }
                else {
                    Drupal::logger('rir_notifier')
                      ->debug('No subscribers found');
                }
            }
            else {
                Drupal::logger('rir_notifier')
                  ->debug('No adverts found for reference: ' . $reference);
            }
        }
    }
}