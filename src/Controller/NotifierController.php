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

      $response = new AjaxResponse();
      $webform = Webform::load('notification_subscription')->getSubmissionForm();
      $response->addCommand(new OpenModalDialogCommand($this->t('Free Email Alert' . $this->url), $webform, ['width'=> '80%']));
      return $response;
  }
}