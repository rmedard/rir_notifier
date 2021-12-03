<?php
/**
 * Created by PhpStorm.
 * User: medard
 * Date: 05.08.17
 * Time: 17:18
 */

namespace Drupal\rir_notifier\Plugin\Block;


use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class SearchSubscribeBlock
 *
 * @package Drupal\rir_notifier\Plugin\Block
 * @Block(
 *   id = "rir_search_subscribe_block",
 *   admin_label = @Translation("RiR Search Subscribe Block"),
 *   category = @Translation("Custom RIR Blocks")
 * )
 */
class SearchSubscribeBlock extends BlockBase {

  /**
   * Builds and returns the renderable array for this block plugin.
   *
   * If a block should not be rendered because it has no content, then this
   * method must also ensure to return no content: it must then only return an
   * empty array, or an empty array with #cache set (with cacheability metadata
   * indicating the circumstances for it being empty).
   *
   * @return array
   *   A renderable array representing the content of the block.
   *
   * @see \Drupal\block\BlockViewBuilder
   */
  #[ArrayShape(['#theme' => "string"])]
  public function build(): array
  {
    return [
      '#theme' => 'rir_subscribe_search'
    ];
  }
}
