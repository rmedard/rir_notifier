<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 03.08.17
 * Time: 16:03
 */

namespace Drupal\rir_notifier\Controller;


use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\webform\Entity\Webform;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NotifierController extends ControllerBase {

  protected $formBuilder;
  protected $url;

  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
    $this->url = \Drupal::request()->getRequestUri();
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  public function openSubscriptionModal(){
      $districts = explode("-", \Drupal::request()->query->get('districts'));
      $values = [
        'webform_id' => 'notification_subscription',
        'data' => [
          'notif_districts' => $districts
        ]
      ];

      $response = new AjaxResponse();
      $webform = Webform::load($values['webform_id'])->getSubmissionForm($values);
      $response->addCommand(new OpenModalDialogCommand($this->t('Free Email Alert' . $districts), $webform, ['width'=> '80%']));
      return $response;
  }
}