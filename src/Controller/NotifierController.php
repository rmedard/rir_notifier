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

  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  public function openSubscriptionModal(){
      $options = array(
        'width' => '80%',
      );
      $bedrooms = \Drupal::request()->query->get('field_advert_bedrooms_value');
      $propertyType = \Drupal::request()->query->get('field_advert_property_type_value');
      $tee = "test";

      $response = new AjaxResponse();
      $webform = Webform::load('notification_subscription')->getSubmissionForm();
      $response->addCommand(new OpenModalDialogCommand($this->t('Free Email Alert' . $tee), $webform, ['width'=> '80%']));
      return $response;
  }
}