<?php

namespace ride\web\cms\controller\backend;

use ride\library\cms\node\Node;
use ride\library\cms\node\NodeProperty;
use ride\library\i18n\translator\Translator;

/**
 * Abstract controller for a node type
 */
abstract class AbstractNodeTypeController extends AbstractBackendController {

    /**
     * Value for the inherited value
     * @var string
     */
    const OPTION_INHERITED = 'inherited';

    /**
     * Processes the inherited value for the provided option value
     * @param mixed $value
     * @return string
     */
    protected function getOptionValueFromForm($value) {
        if ($value == self::OPTION_INHERITED || (is_array($value) && in_array(self::OPTION_INHERITED, $value))) {
            return null;
        }

        return $value;
    }

    /**
     * Gets the form value for the theme option
     * @param \ride\library\cms\node\Node $node
     * @return string
     */
    protected function getThemeValueFromNode(Node $node) {
        $theme = $node->get(Node::PROPERTY_THEME, null, false);
        if (!$theme && $node->hasParent()) {
            $theme = self::OPTION_INHERITED;
        }

        return $theme;
    }

    /**
     * Gets the available locales options
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
     * @param array $locales
     * @return array Array with the publish code as key and the translation as
     * value
     */
    protected function getThemeOptions(Node $node, Translator $translator, array $themes) {
        $options = array();

        $parentNode = $node->getParentNode();
        if ($parentNode) {
            $inheritedValue = $parentNode->get(Node::PROPERTY_THEME, null, true, true);

            if (isset($themes[$inheritedValue])) {
                $inheritedValue = $themes[$inheritedValue]->getDisplayName();
            }

            $options[self::OPTION_INHERITED] = $translator->translate('label.inherited') . ' (' . $inheritedValue . ')';
        }

        foreach ($themes as $id => $theme) {
            $options[$id] = $theme->getDisplayName();
        }

        return $options;
    }

    /**
     * Gets the form value for the available locales options
     * @param \ride\library\cms\node\Node $node
     * @return array
     */
    protected function getLocalesValueFromNode(Node $node) {
        $value = array();

        $availableLocales = $node->get(Node::PROPERTY_LOCALES, '', false);
        if ($availableLocales == Node::LOCALES_ALL || (!$availableLocales && !$node->hasParent())) {
            $value[Node::LOCALES_ALL] = Node::LOCALES_ALL;
        } elseif ($availableLocales && $availableLocales != Node::LOCALES_ALL) {
            $locales = explode(NodeProperty::LIST_SEPARATOR, $availableLocales);

            $value = array();
            foreach ($locales as $locale) {
                $locale = trim($locale);

                $value[$locale] = $locale;
            }
        } else {
            $value[self::OPTION_INHERITED] = self::OPTION_INHERITED;
        }

        return $value;
    }

    /**
     * Gets the available locales options
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
     * @param array $locales
     * @return array Array with the publish code as key and the translation as
     * value
     */
    protected function getLocalesOptions(Node $node, Translator $translator, array $locales) {
        $options = array();

        $parentNode = $node->getParentNode();
        if ($parentNode) {
            $inheritedValue = $parentNode->get(Node::PROPERTY_LOCALES, Node::LOCALES_ALL, true, true);

            $options[self::OPTION_INHERITED] = $translator->translate('label.inherited') . ' (' . $inheritedValue . ')';
        }

        $options[Node::LOCALES_ALL] = $translator->translate('label.locales.all');
        foreach ($locales as $locale) {
            $options[$locale] = $translator->translate('language.' . $locale);
        }

        return $options;
    }

}