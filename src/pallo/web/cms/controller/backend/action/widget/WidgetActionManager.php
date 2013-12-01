<?php

namespace pallo\web\cms\controller\backend\action\widget;

use pallo\library\cms\exception\CmsException;

/**
 * Manager of the widget actions
 */
class WidgetActionManager {

    /**
     * Array with WidgetAction objects as value and their name as key
     * @var array
     */
    protected $widgetActions;

    /**
     * Construct this manager
     * @return null
     */
    public function __construct() {
        $this->widgetActions = array();
    }

    /**
     * Gets the available widget actions
     * @return array Array with the name of the action as key and the
     * implementation as value
     */
    public function getWidgetActions() {
        return $this->widgetActions;
    }

    /**
     * Gets the implementation of a widget action
     * @param string $name
     * @return WidgetAction
     */
    public function getWidgetAction($name) {
        if (!$this->hasWidgetAction($name)) {
            throw new CmsException('Could not get widget action ' . $name . ': action is not registered');
        }

        return $this->widgetActions[$name];
    }

    /**
     * Checks whether a widget action is available in this manager
     * @param string $name Name of the widget action
     * @return boolean True if the widget action is added, false otherwise
     */
    public function hasWidgetAction($name) {
        return isset($this->widgetActions[$name]);
    }

    /**
     * Adds a widget action to this manager
     * @param WidgetAction $widgetAction
     * @return null
     */
    public function addWidgetAction(WidgetAction $widgetAction) {
        $this->widgetActions[$widgetAction->getName()] = $widgetAction;
    }

    /**
     * Removes a widget action from this manager
     * @param string $name Name of the widget action
     * @return null
     */
    public function removeWidgetAction($name) {
        if ($this->hasWidgetAction($name)) {
            unset($this->widgetActions[$name]);
        }
    }

}