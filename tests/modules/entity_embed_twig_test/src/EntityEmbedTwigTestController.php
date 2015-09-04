<?php

/**
 * @file
 * Contains \Drupal\entity_embed_twig_test\EntityEmbedTwigTestController.
 */

namespace Drupal\entity_embed_twig_test;

/**
 * Controller routines for Twig theme test routes.
 */
class EntityEmbedTwigTestController {

  /**
   * Menu callback for testing entity_embed twig extension using entity ID.
   */
  public function idRender() {
    return array(
      '#theme' => 'entity_embed_twig_test',
      '#entity_type' => 'node',
      '#id' => '1',
    );
  }

  /**
   * Menu callback for testing entity_embed twig extension using 'label' display plugin.
   */
  public function labelPluginRender() {
    return array(
      '#theme' => 'entity_embed_twig_test',
      '#entity_type' => 'node',
      '#id' => '1',
      '#display_plugin' => 'entity_reference:entity_reference_label',
    );
  }

  /**
   * Menu callback for testing entity_embed twig extension using 'label' display plugin without linking to the node.
   */
  public function labelPluginNoLinkRender() {
    return array(
      '#theme' => 'entity_embed_twig_test',
      '#entity_type' => 'node',
      '#id' => '1',
      '#display_plugin' => 'entity_reference:entity_reference_label',
      '#display_settings' => array('link' => 0),
    );
  }

}
