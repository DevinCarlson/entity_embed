/**
 * @file
 * Drupal Entity embed plugin.
 */

(function ($, Drupal, CKEDITOR) {

  "use strict";

  CKEDITOR.plugins.add('drupalentity', {
    requires: 'widget',

    beforeInit: function (editor) {
      // Configure CKEditor DTD for custom drupal-entity element.
      // @see https://www.drupal.org/node/2448449#comment-9717735
      CKEDITOR.dtd['drupal-entity'] = {'#': 1};
      CKEDITOR.dtd.$block['drupal-entity'] = 1;
      CKEDITOR.dtd.$blockLimit['drupal-entity'] = 1;
      // @todo It should be possible to display embedded entities inline...
      //CKEDITOR.dtd.$inline['drupal-entity'] = 1;
      CKEDITOR.dtd.$nonEditable['drupal-entity'] = 1;
      CKEDITOR.dtd.$object['drupal-entity'] = 1;
    },

    init: function (editor) {
      // Generic command for adding/editing entities of all types.
      editor.addCommand('editdrupalentity', {
        allowedContent: 'drupal-entity[*]',
        requiredContent: 'drupal-entity[*]',
        modes: { wysiwyg : 1 },
        canUndo: true,
        exec: function (editor, data) {
          data = data || {};

          var existingElement = getSelectedEmbeddedEntity(editor);

          var existingValues = {};
          if (existingElement && existingElement.$ && existingElement.$.firstChild) {
            var embedDOMElement = existingElement.$.firstChild;
            // Populate array with the entity's current attributes.
            var attribute = null, attributeName;
            for (var key = 0; key < embedDOMElement.attributes.length; key++) {
              attribute = embedDOMElement.attributes.item(key);
              attributeName = attribute.nodeName.toLowerCase();
              if (attributeName.substring(0, 15) === 'data-cke-saved-') {
                continue;
              }
              existingValues[attributeName] = existingElement.data('cke-saved-' + attributeName) || attribute.nodeValue;
            }
          }

          var entity_label = data.label ? data.label : existingValues['data-entity-label'];
          var embed_button_id = data.id ? data.id : existingValues['data-embed-button'];

          var dialogSettings = {
            title: existingElement ? 'Edit ' + entity_label : 'Insert ' + entity_label,
            dialogClass: 'entity-select-dialog',
            resizable: false,
            minWidth: 800
          };

          var saveCallback = function (values) {
            var entityElement = editor.document.createElement('drupal-entity');
            var attributes = values.attributes;
            for (var key in attributes) {
              entityElement.setAttribute(key, attributes[key]);
            }
            editor.insertHtml(entityElement.getOuterHtml());
            if (existingElement) {
              // Detach the behaviors that were attached when the entity content
              // was inserted.
              Drupal.runEmbedBehaviors('detach', existingElement.$);
              existingElement.remove();
            }
          };

          // Open the entity embed dialog for corresponding EmbedButton.
          Drupal.ckeditor.openDialog(editor, Drupal.url('entity-embed/dialog/' + editor.config.drupal.format + '/' + embed_button_id), existingValues, saveCallback, dialogSettings);
        }
      });

      // Register the entity embed widget.
      editor.widgets.add('drupalentity', {
        // Minimum HTML which is required by this widget to work.
        allowedContent: 'drupal-entity[*]',
        requiredContent: 'drupal-entity[*]',

        // Simply recognize the element as our own. The inner markup if fetched
        // and inserted the init() callback, since it requires the actual DOM
        // element.
        upcast: function (element) {
          var attributes = element.attributes;
          if (attributes['data-entity-type'] === undefined || (attributes['data-entity-id'] === undefined && attributes['data-entity-uuid'] === undefined) || (attributes['data-view-mode'] === undefined && attributes['data-entity-embed-display'] === undefined)) {
            return;
          }
          // Generate an ID for the element, so that we can use the Ajax
          // framework.
          element.attributes.id = generateEmbedId();
          return element;
        },

        // Fetch the rendered entity.
        init: function () {
          var element = this.element;
          var $element = $(element.$);
          // Use the Ajax framework to fetch the HTML, so that we can retrieve
          // out-of-band assets (JS, CSS...).
          var entityEmbedPreview = new Drupal.ajax({
            base: $element.attr('id'),
            element: $element,
            url: Drupal.url('embed/preview/' + editor.config.drupal.format + '?' + $.param({
              value: element.getOuterHtml()
            })),
            progress: {type: 'none'},
            // Use a custom event to trigger the call.
            event: 'entity_embed_dummy_event'
          });
          entityEmbedPreview.execute();
        },

        // Downcast the element.
        downcast: function (element) {
          // Only keep the wrapping element.
          element.setHtml('');
          // Remove the auto-generated ID.
          delete element.attributes.id;
          return element;
        }
      });

      // Register the toolbar buttons.
      if (editor.ui.addButton) {
        for (var key in editor.config.DrupalEntity_buttons) {
          var button = editor.config.DrupalEntity_buttons[key];
          editor.ui.addButton(button.id, {
            label: button.label,
            data: button,
            click: function(editor) {
              editor.execCommand('editdrupalentity', this.data);
            },
            icon: button.image
          });
        }
      }

      // Register context menu option for editing widget.
      if (editor.contextMenu) {
        editor.addMenuGroup('drupalentity');
        editor.addMenuItem('drupalentity', {
          label: Drupal.t('Edit Entity'),
          icon: this.path + 'entity.png',
          command: 'editdrupalentity',
          group: 'drupalentity'
        });

        editor.contextMenu.addListener(function(element) {
          if (isEditableEntityWidget(editor, element)) {
            return { drupalentity: CKEDITOR.TRISTATE_OFF };
          }
        });
      }

      // Execute widget editing action on double click.
      editor.on('doubleclick', function (evt) {
        var element = getSelectedEmbeddedEntity(editor) || evt.data.element;

        if (isEditableEntityWidget(editor, element)) {
          editor.execCommand('editdrupalentity');
        }
      });
    }
  });

  /**
   * Get the surrounding drupalentity widget element.
   *
   * @param {CKEDITOR.editor} editor
   */
  function getSelectedEmbeddedEntity(editor) {
    var selection = editor.getSelection();
    var selectedElement = selection.getSelectedElement();
    if (isEmbeddedEntityWidget(editor, selectedElement)) {
      return selectedElement;
    }

    return null;
  }

  /**
   * Returns whether or not the given element is a drupalentity widget.
   *
   * @param {CKEDITOR.editor} editor
   * @param {CKEDITOR.htmlParser.element} element
   */
  function isEmbeddedEntityWidget (editor, element) {
    var widget = editor.widgets.getByElement(element, true);
    return widget && widget.name === 'drupalentity';
  }

  /**
   * Returns whether or not the given element is a editable drupalentity widget.
   *
   * This requires the widget to have the data-embed-button attribute present.
   *
   * @param {CKEDITOR.editor} editor
   * @param {CKEDITOR.htmlParser.element} element
   */
  function isEditableEntityWidget (editor, element) {
    return isEmbeddedEntityWidget(editor, element) && element.$.firstChild.attributes.hasOwnProperty('data-embed-button');
  }

  /**
   * Generates unique HTML IDs for the widgets.
   *
   * @returns {string}
   */
  function generateEmbedId() {
    if (typeof generateEmbedId.counter == 'undefined') {
      generateEmbedId.counter = 0;
    }
    return 'entity-embed-' + generateEmbedId.counter++;
  }

})(jQuery, Drupal, CKEDITOR);
