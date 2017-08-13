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
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use Drupal;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Mailchimp;
use function urlencode;

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

        $detailsRequestCategory = Node::create([
          'type' => 'details_request_category',
          'title' => $data->reference,
          'field_mailchimp_list_id' => $responseData['list_id'],
          'field_mailchimp_category_id' => $responseData['id'],
          'field_dr_reference' => $responseData['title']
        ]);
        $detailsRequestCategory->save();
//
//        $url = 'https://us16.api.mailchimp.com/3.0/lists/'.$mailChimpListId.'/interest-categories';
//        $ch = curl_init($url);
//        curl_setopt_array($ch, array(
//          CURLOPT_POST => TRUE,
//          CURLOPT_RETURNTRANSFER => TRUE,
//          CURLOPT_HTTPHEADER => array(
//            'Content-Type: application/json',
//            'Authorization: OAuth ' . $token
//          ),
//          CURLOPT_POSTFIELDS => json_encode()
//        ));
//        $response = curl_exec($ch);
//        if ($response === FALSE){
//          Drupal::logger('rir_notifier')->error(curl_error($ch));
//        } else {
//          Drupal::logger('rir_notifier')->notice($response);
//          $responseData = json_decode($response, TRUE);
//          $detailsRequestCategory = Node::create([
//            'type' => 'details_request_category',
//            'title' => $data->reference,
//            'field_mailchimp_list_id' => $responseData['list_id'],
//            'field_mailchimp_category_id' => $responseData['id'],
//            'field_dr_reference' => $responseData['title']
//          ]);
//          $detailsRequestCategory->save();
//        }
      } else {

      }
    }

  }

  private function authorize(){
    /**
     * Mailchimp Lib doc: https://github.com/Jhut89/Mailchimp-API-3.0-PHP
     */
    $mailChimpAPIKey = '32e34053c5d17d18bf833e1c90af369e-us16';
    $mailchimp = new Mailchimp($mailChimpAPIKey);
    $clientID = '679132406599';
    $clientSecret = 'a4a75098d661c74574a825fcc0cd2758797934924362538593';
    /**
     * To get csrf token: Use Postman: GET request
     * https://login.mailchimp.com/oauth2/authorize?response_type=code&client_id=679132406599&redirect_uri=http%3A%2F%2Frirdev.tk%2Foauth%2Fcomplete.php
     */
    $csrf_token = '561b3b9406931a97ea2249f27ace5b88cd3f6daf';

    $url = 'https://login.mailchimp.com/oauth2/token';

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
      CURLOPT_POST => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_POSTFIELDS => 'grant_type=authorization_code&client_id='.$clientID.'&client_secret='
        .$clientSecret.'&redirect_uri='.urlencode('http://rirdev.tk/oauth/complete.php').'&code='.$csrf_token,
    ));
    $response = curl_exec($ch);
    if ($response === FALSE){
      Drupal::logger('rir_notifier')->error(curl_error($ch));
      return NULL;
    } else {
//      $responseData = json_decode($response, TRUE);
      Drupal::logger('rir_notifier')->notice($response);
//      return $responseData['access_token'];
    }
  }
}