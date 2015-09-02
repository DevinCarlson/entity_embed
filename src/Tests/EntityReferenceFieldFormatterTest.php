<?php

/**
 * @file
 * Contains \Drupal\entity_embed\Tests\EntityReferenceFieldFormatterTest.
 */

namespace Drupal\entity_embed\Tests;

use Drupal\Core\Form\FormState;
use Drupal\entity_embed\EntityHelperTrait;

/**
 * Tests the entity reference field formatters provided by entity_embed.
 *
 * @group entity_embed
 */
class EntityReferenceFieldFormatterTest extends EntityEmbedTestBase {
  use EntityHelperTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('entity_embed', 'node');

  /**
   * The test 'node' entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The test 'menu' entity.
   *
   * @var \Drupal\Core\Menu\MenuInterface
   */
  protected $menu;

  protected function setUp() {
    parent::setUp();

    $this->entity = $this->loadEntity('node', $this->node->uuid());

    // Add a new menu entity which does not has a view controller.
    $this->menu = entity_create('menu', array(
      'id' => 'menu_name',
      'label' => 'Label',
      'description' => 'Description text',
    ));
    $this->menu->save();
  }

  /**
   * Tests entity reference field formatters.
   */
  public function testEntityReferenceFieldFormatter() {
    // Ensure that entity reference field formatters are available as display
    // plugins.
    $plugin_options = $this->displayPluginManager()->getDefinitionOptionsForEntity($this->entity);
    // Ensure that 'default' plugin is available.
    $this->assertTrue(array_key_exists('default', $plugin_options), "The 'Default' plugin is available.");
    // Ensure that 'entity_reference' plugins are available.
    $this->assertTrue(array_key_exists('entity_reference:entity_reference_entity_id', $plugin_options), "The 'Entity ID' plugin is available.");
    $this->assertTrue(array_key_exists('entity_reference:entity_reference_entity_view', $plugin_options), "The 'Rendered entity' plugin is available.");
    $this->assertTrue(array_key_exists('entity_reference:entity_reference_label', $plugin_options), "The 'Label' plugin is available.");

    // Ensure that correct form attributes are returned for 'default' plugin.
    $form = array();
    $form_state = new FormState();
    $display = $this->displayPluginManager()->createInstance('default', array());
    $display->setContextValue('entity', $this->entity);
    $conf_form = $display->buildConfigurationForm($form, $form_state);
    $this->assertIdentical(array_keys($conf_form), array('view_mode'));
    $this->assertIdentical($conf_form['view_mode']['#type'], 'select');
    $this->assertIdentical($conf_form['view_mode']['#title'], 'View mode');

    // Ensure that correct form attributes are returned for
    // 'entity_reference:entity_reference_entity_id' plugin.
    $form = array();
    $form_state = new FormState();
    $display = $this->displayPluginManager()->createInstance('entity_reference:entity_reference_entity_id', array());
    $display->setContextValue('entity', $this->entity);
    $conf_form = $display->buildConfigurationForm($form, $form_state);
    $this->assertIdentical(array_keys($conf_form), array());

    // Ensure that correct form attributes are returned for
    // 'entity_reference:entity_reference_entity_view' plugin.
    $form = array();
    $form_state = new FormState();
    $display = $this->displayPluginManager()->createInstance('entity_reference:entity_reference_entity_view', array());
    $display->setContextValue('entity', $this->entity);
    $conf_form = $display->buildConfigurationForm($form, $form_state);
    $this->assertIdentical($conf_form['view_mode']['#type'], 'select');
    $this->assertIdentical($conf_form['view_mode']['#title'], 'View mode');

    // Ensure that correct form attributes are returned for
    // 'entity_reference:entity_reference_label' plugin.
    $form = array();
    $form_state = new FormState();
    $display = $this->displayPluginManager()->createInstance('entity_reference:entity_reference_label', array());
    $display->setContextValue('entity', $this->entity);
    $conf_form = $display->buildConfigurationForm($form, $form_state);
    $this->assertIdentical(array_keys($conf_form), array('link'));
    $this->assertIdentical($conf_form['link']['#type'], 'checkbox');
    $this->assertIdentical($conf_form['link']['#title'], 'Link label to the referenced entity');

    // Ensure that 'Rendered Entity' plugin is not available for an entity not
    // having a view controller.
    $plugin_options = $this->displayPluginManager()->getDefinitionOptionsForEntity($this->menu);
    $this->assertFalse(array_key_exists('entity_reference:entity_reference_entity_view', $plugin_options), "The 'Rendered entity' plugin is not available.");
  }

  /**
   * Tests entity embed filter using entity reference display plugins.
   */
  public function testFilterEntityReferencePlugins() {
    // Test entity embed using 'Label' display plugin.
    $content = '<div data-entity-type="node" data-entity-uuid="' . $this->node->uuid() . '" data-entity-embed-display="entity_reference:entity_reference_label" data-entity-embed-settings=\'{"link":1}\'>This placeholder should not be rendered.</div>';
    $settings = array();
    $settings['type'] = 'page';
    $settings['title'] = 'Test entity embed with entity_reference:entity_reference_label display plugin';
    $settings['body'] = array(array('value' => $content, 'format' => 'custom_format'));
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertText($this->node->title->value, 'Title of the embedded node exists in page.');
    $this->assertNoText($this->node->body->value, 'Body of embedded node does not exists in page.');
    $this->assertNoText(strip_tags($content), 'Placeholder does not appears in the output when embed is successful.');
    $this->assertLinkByHref('node/' . $this->node->id(), 0, 'Link to the embedded node exists.');

    // Test entity embed using 'Entity ID' display plugin.
    $content = '<div data-entity-type="node" data-entity-uuid="' . $this->node->uuid() . '" data-entity-embed-display="entity_reference:entity_reference_entity_id">This placeholder should not be rendered.</div>';
    $settings = array();
    $settings['type'] = 'page';
    $settings['title'] = 'Test entity embed with entity_reference:entity_reference_entity_id display plugin';
    $settings['body'] = array(array('value' => $content, 'format' => 'custom_format'));
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertText($this->node->id(), 'ID of the embedded node exists in page.');
    $this->assertNoText($this->node->title->value, 'Title of the embedded node does not exists in page.');
    $this->assertNoText($this->node->body->value, 'Body of embedded node does not exists in page.');
    $this->assertNoText(strip_tags($content), 'Placeholder does not appears in the output when embed is successful.');
    $this->assertNoLinkByHref('node/' . $this->node->id(), 'Link to the embedded node does not exists.');

    // Test entity embed using 'Rendered entity' display plugin.
    $content = '<div data-entity-type="node" data-entity-uuid="' . $this->node->uuid() . '" data-entity-embed-display="entity_reference:entity_reference_entity_view" data-entity-embed-settings=\'{"view_mode":"teaser"}\'>This placeholder should not be rendered.</div>';
    $settings = array();
    $settings['type'] = 'page';
    $settings['title'] = 'Test entity embed with entity_reference:entity_reference_label display plugin';
    $settings['body'] = array(array('value' => $content, 'format' => 'custom_format'));
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertText($this->node->body->value, 'Body of embedded node does not exists in page.');
    $this->assertNoText(strip_tags($content), 'Placeholder does not appears in the output when embed is successful.');
  }

}
