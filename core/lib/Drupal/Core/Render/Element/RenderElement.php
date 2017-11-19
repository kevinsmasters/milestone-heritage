<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Provides a base class for render element plugins.
 *
 * Render elements are referenced in render arrays; see the
 * @link theme_render Render API topic @endlink for an overview of render
 * arrays and render elements.
 *
 * The elements of render arrays are divided up into properties (whose keys
 * start with #) and children (whose keys do not start with #). The properties
 * provide data or settings that are used in rendering. Some properties are
 * specific to a particular type of render element, some are available for any
 * render element, and some are available for any form input element. A list of
 * the properties that are available for all render elements follows; the
 * properties that are for all form elements are documented on
 * \Drupal\Core\Render\Element\FormElement, and properties specific to a
 * particular element are documented on that element's class. See the
 * @link theme_render Render API topic @endlink for a list of the most
 * commonly-used properties.
 *
 * Many of the properties are strings that are displayed to users. These
 * strings, if they are literals provided by your module, should be
 * internationalized and translated; see the
 * @link i18n Internationalization topic @endlink for more information. Note
 * that although in the properies list that follows, they are designated to be
 * of type string, they would generally end up being
 * \Drupal\Core\StringTranslation\TranslatableMarkup objects instead.
 *
 * Here is the list of the properties used during the rendering of all render
 * elements:
 * - #access: (bool) Whether the element is accessible or not. When FALSE,
 *   the element is not rendered and user-submitted values are not taken
 *   into consideration.
 * - #access_callback: A callable or function name to call to check access.
 *   Argument: element.
 * - #allowed_tags: (array) Array of allowed HTML tags for XSS filtering of
 *   #markup, #prefix, #suffix, etc.
 * - #attached: (array) Array of attachments associated with the element.
 *   See the "Attaching libraries in render arrays" section of the
 *   @link theme_render Render API topic @endlink for an overview, and
 *   \Drupal\Core\Render\AttachmentsResponseProcessorInterface::processAttachments
 *   for a list of what this can contain. Besides this list, it may also contain
 *   a 'placeholders' element; see the Placeholders section of the
 *   @link theme_render Render API topic @endlink for an overview.
 * - #attributes: (array) HTML attributes for the element. The first-level
 *   keys are the attribute names, such as 'class', and the attributes are
 *   usually given as an array of string values to apply to that attribute
 *   (the rendering system will concatenate them together into a string in
 *   the HTML output).
 * - #cache: (array) Cache information. See the Caching section of the
 *   @link theme_render Render API topic @endlink for more information.
 * - #children: (array, internal) Array of child elements of this element.
 *   Set and used during the rendering process.
 * - #create_placeholder: (bool) TRUE if the element has placeholders that
 *   are generated by #lazy_builder callbacks. Set internally during rendering
 *   in some cases. See also #attached.
 * - #defaults_loaded: (bool) Set to TRUE during rendering when the defaults
 *   for the element #type have been added to the element.
 * - #id: (string) The HTML ID on the element. This is automatically set for
 *   form elements, but not for all render elements; you can override the
 *   default value or add an ID by setting this property.
 * - #lazy_builder: (array) Array whose first element is a lazy building
 *   callback (callable), and whose second is an array of scalar arguments to
 *   the callback. To use lazy building, the element array must be very
 *   simple: no properties except #lazy_builder, #cache, #weight, and
 *   #create_placeholder, and no children. A lazy builder callback typically
 *   generates #markup and/or placeholders; see the Placeholders section of the
 *   @link theme_render Render API topic @endlink for information about
 *   placeholders.
 * - #markup: (string) During rendering, this will be set to the HTML markup
 *   output. It can also be set on input, as a fallback if there is no
 *   theming for the element. This will be filtered for XSS problems during
 *   rendering; see also #plain_text and #allowed_tags.
 * - #plain_text: (string) Elements can set this instead of #markup. All HTML
 *   tags will be escaped in this text, and if both #plain_text and #markup
 *   are provided, #plain_text is used.
 * - #post_render: (array) Array of callables or function names, which are
 *   called after the element is rendered. Arguments: rendered element string,
 *   children.
 * - #pre_render: (array) Array of callables or function names, which are
 *   called just before the element is rendered. Argument: $element.
 *   Return value: an altered $element.
 * - #prefix: (string) Text to render before the entire element output. See
 *   also #suffix. If it is not already wrapped in a safe markup object, will
 *   be filtered for XSS safety.
 * - #printed: (bool, internal) Set to TRUE when an element and its children
 *   have been rendered.
 * - #render_children: (bool, internal) Set to FALSE by the rendering process
 *   if the #theme call should be bypassed (normally, the theme is used to
 *   render the children). Set to TRUE by the rendering process if the children
 *   should be rendered by rendering each one separately and concatenating.
 * - #suffix: (string) Text to render after the entire element output. See
 *   also #prefix. If it is not already wrapped in a safe markup object, will
 *   be filtered for XSS safety.
 * - #theme: (string) Name of the theme hook to use to render the element.
 *   A default is generally set for elements; users of the element can
 *   override this (typically by adding __suggestion suffixes).
 * - #theme_wrappers: (array) Array of theme hooks, which are invoked
 *   after the element and children are rendered, and before #post_render
 *   functions.
 * - #type: (string) The machine name of the type of render/form element.
 * - #weight: (float) The sort order for rendering, with lower numbers coming
 *   before higher numbers. Default if not provided is zero; elements with
 *   the same weight are rendered in the order they appear in the render
 *   array.
 *
 * @see \Drupal\Core\Render\Annotation\RenderElement
 * @see \Drupal\Core\Render\ElementInterface
 * @see \Drupal\Core\Render\ElementInfoManager
 * @see plugin_api
 *
 * @ingroup theme_render
 */
abstract class RenderElement extends PluginBase implements ElementInterface {

  /**
   * {@inheritdoc}
   */
  public static function setAttributes(&$element, $class = []) {
    if (!empty($class)) {
      if (!isset($element['#attributes']['class'])) {
        $element['#attributes']['class'] = [];
      }
      $element['#attributes']['class'] = array_merge($element['#attributes']['class'], $class);
    }
    // This function is invoked from form element theme functions, but the
    // rendered form element may not necessarily have been processed by
    // \Drupal::formBuilder()->doBuildForm().
    if (!empty($element['#required'])) {
      $element['#attributes']['class'][] = 'required';
      $element['#attributes']['required'] = 'required';
      $element['#attributes']['aria-required'] = 'true';
    }
    if (isset($element['#parents']) && isset($element['#errors']) && !empty($element['#validated'])) {
      $element['#attributes']['class'][] = 'error';
      $element['#attributes']['aria-invalid'] = 'true';
    }
  }

  /**
   * Adds members of this group as actual elements for rendering.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element.
   *
   * @return array
   *   The modified element with all group members.
   */
  public static function preRenderGroup($element) {
    // The element may be rendered outside of a Form API context.
    if (!isset($element['#parents']) || !isset($element['#groups'])) {
      return $element;
    }

    // Inject group member elements belonging to this group.
    $parents = implode('][', $element['#parents']);
    $children = Element::children($element['#groups'][$parents]);
    if (!empty($children)) {
      foreach ($children as $key) {
        // Break references and indicate that the element should be rendered as
        // group member.
        $child = (array) $element['#groups'][$parents][$key];
        $child['#group_details'] = TRUE;
        // Inject the element as new child element.
        $element[] = $child;

        $sort = TRUE;
      }
      // Re-sort the element's children if we injected group member elements.
      if (isset($sort)) {
        $element['#sorted'] = FALSE;
      }
    }

    if (isset($element['#group'])) {
      // Contains form element summary functionalities.
      $element['#attached']['library'][] = 'core/drupal.form';

      $group = $element['#group'];
      // If this element belongs to a group, but the group-holding element does
      // not exist, we need to render it (at its original location).
      if (!isset($element['#groups'][$group]['#group_exists'])) {
        // Intentionally empty to clarify the flow; we simply return $element.
      }
      // If we injected this element into the group, then we want to render it.
      elseif (!empty($element['#group_details'])) {
        // Intentionally empty to clarify the flow; we simply return $element.
      }
      // Otherwise, this element belongs to a group and the group exists, so we do
      // not render it.
      elseif (Element::children($element['#groups'][$group])) {
        $element['#printed'] = TRUE;
      }
    }

    return $element;
  }

  /**
   * Form element processing handler for the #ajax form property.
   *
   * This method is useful for non-input elements that can be used in and
   * outside the context of a form.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   *
   * @see self::preRenderAjaxForm()
   */
  public static function processAjaxForm(&$element, FormStateInterface $form_state, &$complete_form) {
    return static::preRenderAjaxForm($element);
  }

  /**
   * Adds Ajax information about an element to communicate with JavaScript.
   *
   * If #ajax is set on an element, this additional JavaScript is added to the
   * page header to attach the Ajax behaviors. See ajax.js for more information.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used:
   *   - #ajax['event']
   *   - #ajax['prevent']
   *   - #ajax['url']
   *   - #ajax['callback']
   *   - #ajax['options']
   *   - #ajax['wrapper']
   *   - #ajax['parameters']
   *   - #ajax['effect']
   *   - #ajax['accepts']
   *
   * @return array
   *   The processed element with the necessary JavaScript attached to it.
   */
  public static function preRenderAjaxForm($element) {
    // Skip already processed elements.
    if (isset($element['#ajax_processed'])) {
      return $element;
    }
    // Initialize #ajax_processed, so we do not process this element again.
    $element['#ajax_processed'] = FALSE;

    // Nothing to do if there are no Ajax settings.
    if (empty($element['#ajax'])) {
      return $element;
    }

    // Add a data attribute to disable automatic refocus after ajax call.
    if (!empty($element['#ajax']['disable-refocus'])) {
      $element['#attributes']['data-disable-refocus'] = "true";
    }


    // Add a reasonable default event handler if none was specified.
    if (isset($element['#ajax']) && !isset($element['#ajax']['event'])) {
      switch ($element['#type']) {
        case 'submit':
        case 'button':
        case 'image_button':
          // Pressing the ENTER key within a textfield triggers the click event of
          // the form's first submit button. Triggering Ajax in this situation
          // leads to problems, like breaking autocomplete textfields, so we bind
          // to mousedown instead of click.
          // @see https://www.drupal.org/node/216059
          $element['#ajax']['event'] = 'mousedown';
          // Retain keyboard accessibility by setting 'keypress'. This causes
          // ajax.js to trigger 'event' when SPACE or ENTER are pressed while the
          // button has focus.
          $element['#ajax']['keypress'] = TRUE;
          // Binding to mousedown rather than click means that it is possible to
          // trigger a click by pressing the mouse, holding the mouse button down
          // until the Ajax request is complete and the button is re-enabled, and
          // then releasing the mouse button. Set 'prevent' so that ajax.js binds
          // an additional handler to prevent such a click from triggering a
          // non-Ajax form submission. This also prevents a textfield's ENTER
          // press triggering this button's non-Ajax form submission behavior.
          if (!isset($element['#ajax']['prevent'])) {
            $element['#ajax']['prevent'] = 'click';
          }
          break;

        case 'password':
        case 'textfield':
        case 'number':
        case 'tel':
        case 'textarea':
          $element['#ajax']['event'] = 'blur';
          break;

        case 'radio':
        case 'checkbox':
        case 'select':
        case 'date':
          $element['#ajax']['event'] = 'change';
          break;

        case 'link':
          $element['#ajax']['event'] = 'click';
          break;

        default:
          return $element;
      }
    }

    // Attach JavaScript settings to the element.
    if (isset($element['#ajax']['event'])) {
      $element['#attached']['library'][] = 'core/jquery.form';
      $element['#attached']['library'][] = 'core/drupal.ajax';

      $settings = $element['#ajax'];

      // Assign default settings. When 'url' is set to NULL, ajax.js submits the
      // Ajax request to the same URL as the form or link destination is for
      // someone with JavaScript disabled. This is generally preferred as a way to
      // ensure consistent server processing for js and no-js users, and Drupal's
      // content negotiation takes care of formatting the response appropriately.
      // However, 'url' and 'options' may be set when wanting server processing
      // to be substantially different for a JavaScript triggered submission.
      $settings += [
        'url' => NULL,
        'options' => ['query' => []],
        'dialogType' => 'ajax',
      ];
      if (array_key_exists('callback', $settings) && !isset($settings['url'])) {
        $settings['url'] = Url::fromRoute('<current>');
        // Add all the current query parameters in order to ensure that we build
        // the same form on the AJAX POST requests. For example,
        // \Drupal\user\AccountForm takes query parameters into account in order
        // to hide the password field dynamically.
        $settings['options']['query'] += \Drupal::request()->query->all();
        $settings['options']['query'][FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;
      }

      // @todo Legacy support. Remove in Drupal 8.
      if (isset($settings['method']) && $settings['method'] == 'replace') {
        $settings['method'] = 'replaceWith';
      }

      // Convert \Drupal\Core\Url object to string.
      if (isset($settings['url']) && $settings['url'] instanceof Url) {
        $url = $settings['url']->setOptions($settings['options'])->toString(TRUE);
        BubbleableMetadata::createFromRenderArray($element)
          ->merge($url)
          ->applyTo($element);
        $settings['url'] = $url->getGeneratedUrl();
      }
      else {
        $settings['url'] = NULL;
      }
      unset($settings['options']);

      // Add special data to $settings['submit'] so that when this element
      // triggers an Ajax submission, Drupal's form processing can determine which
      // element triggered it.
      // @see _form_element_triggered_scripted_submission()
      if (isset($settings['trigger_as'])) {
        // An element can add a 'trigger_as' key within #ajax to make the element
        // submit as though another one (for example, a non-button can use this
        // to submit the form as though a button were clicked). When using this,
        // the 'name' key is always required to identify the element to trigger
        // as. The 'value' key is optional, and only needed when multiple elements
        // share the same name, which is commonly the case for buttons.
        $settings['submit']['_triggering_element_name'] = $settings['trigger_as']['name'];
        if (isset($settings['trigger_as']['value'])) {
          $settings['submit']['_triggering_element_value'] = $settings['trigger_as']['value'];
        }
        unset($settings['trigger_as']);
      }
      elseif (isset($element['#name'])) {
        // Most of the time, elements can submit as themselves, in which case the
        // 'trigger_as' key isn't needed, and the element's name is used.
        $settings['submit']['_triggering_element_name'] = $element['#name'];
        // If the element is a (non-image) button, its name may not identify it
        // uniquely, in which case a match on value is also needed.
        // @see _form_button_was_clicked()
        if (!empty($element['#is_button']) && empty($element['#has_garbage_value'])) {
          $settings['submit']['_triggering_element_value'] = $element['#value'];
        }
      }

      // Convert a simple #ajax['progress'] string into an array.
      if (isset($settings['progress']) && is_string($settings['progress'])) {
        $settings['progress'] = ['type' => $settings['progress']];
      }
      // Change progress path to a full URL.
      if (isset($settings['progress']['url']) && $settings['progress']['url'] instanceof Url) {
        $settings['progress']['url'] = $settings['progress']['url']->toString();
      }

      $element['#attached']['drupalSettings']['ajax'][$element['#id']] = $settings;
      $element['#attached']['drupalSettings']['ajaxTrustedUrl'][$settings['url']] = TRUE;

      // Indicate that Ajax processing was successful.
      $element['#ajax_processed'] = TRUE;
    }
    return $element;
  }

  /**
   * Arranges elements into groups.
   *
   * This method is useful for non-input elements that can be used in and
   * outside the context of a form.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element. Note that $element must be taken by reference here, so processed
   *   child elements are taken over into $form_state.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processGroup(&$element, FormStateInterface $form_state, &$complete_form) {
    $parents = implode('][', $element['#parents']);

    // Each details element forms a new group. The #type 'vertical_tabs' basically
    // only injects a new details element.
    $groups = &$form_state->getGroups();
    $groups[$parents]['#group_exists'] = TRUE;
    $element['#groups'] = &$groups;

    // Process vertical tabs group member details elements.
    if (isset($element['#group'])) {
      // Add this details element to the defined group (by reference).
      $group = $element['#group'];
      $groups[$group][] = &$element;
    }

    return $element;
  }

}
