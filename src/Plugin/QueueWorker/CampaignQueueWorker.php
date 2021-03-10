<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 09/09/2017
 * Time: 02:25
 */

namespace Drupal\rir_notifier\Plugin\QueueWorker;


use Drupal;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;
use Exception;

/**
 * Class CampaignQueueWorker
 *
 * @package Drupal\rir_notifier\Plugin\QueueWorker
 * @QueueWorker(
 *  id = "campaigns_processor",
 *  title = "Campaigns Queue Worker",
 *  cron = {"time" = 600}
 * )
 */
class CampaignQueueWorker extends QueueWorkerBase
{

    /**
     * Works on a single queue item.
     *
     * @param mixed $data
     *   The data that was passed to
     *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was
     *   queued.
     *
     * @throws RequeueException
     *   Processing is not yet finished. This will allow another process to
     *   claim the item immediately.
     * @throws Exception
     *   A QueueWorker plugin may throw an exception to indicate there was a
     *   problem. The cron process will log the exception, and leave the item
     *   in
     *   the queue to be processed again later.
     * @throws SuspendQueueException
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
    public function processItem($data)
    {
        if (isset($data)) {
            $mailManager = Drupal::service('plugin.manager.mail');
            $module = 'rir_interface';
            $key = 'send_campaign_email';
            $reply = Drupal::config('system.site')->get('mail');

            $sid = $data->sid;
            $advertIds = $data->advertIds;

            $submission = WebformSubmission::load($sid);
            $adverts = Node::loadMultiple($advertIds);
            if ($submission instanceof WebformSubmissionInterface) {
                $to = $submission->getElementData('notif_firstname') . '<' . $submission->getElementData('notif_email') . '>';
                $params['message'] = Markup::create(getCampaignHtmlContent($sid, $submission->getElementData('notif_firstname'), $adverts));

                $langcode = Drupal::languageManager()->getDefaultLanguage()->getId();
                $send = TRUE;
                $result = $mailManager->mail($module, $key, $to, $langcode, $params, $reply, $send);

                if (intval($result['result']) !== 1) {
                    $message = t('There was a problem sending campaign email to @email', ['@email' => $to]);
                    Drupal::logger('rir_notifier')->error($message);
                } else {
                    $message = t('An campaign email has been sent to @email with advertIds: @ids',
                        ['@email' => $to, '@ids' => implode('|', $advertIds)]);
                    Drupal::logger('rir_notifier')->info($message);
                }
            }
        } else {
            $message = t('No campaigns available to distribute.');
            Drupal::logger('rir_notifier')->info($message);
        }
    }
}