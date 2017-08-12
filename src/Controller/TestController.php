<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 12.08.17
 * Time: 18:19
 */

namespace Drupal\rir_notifier\Controller;


use Drupal\Core\Controller\ControllerBase;

class TestController extends ControllerBase {

  public function testPage(){
    $element = array(
      '#markup' => 'Hello, This is a test page',
    );
    return $element;
  }
}