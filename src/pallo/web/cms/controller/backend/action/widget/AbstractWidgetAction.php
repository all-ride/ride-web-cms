<?php

namespace pallo\web\cms\controller\backend\action\widget;

use pallo\library\cms\node\Node;
use pallo\library\cms\widget\Widget;

use pallo\web\cms\controller\backend\AbstractBackendController;

/**
 * Abstract controller of a widget action
 */
abstract class AbstractWidgetAction extends AbstractBackendController implements WidgetAction {

    /**
     * Get the machine name of this action
     * @return string
     */
    public function getName() {
        return static::NAME;
    }

    /**
     * Get the route of this action
     * @return string
     */
    public function getRoute() {
        return static::ROUTE;
    }

    /**
     * Checks if this action is available for the widget
     * @param pallo\library\cms\node\Node $node
     * @param pallo\library\cms\widget\Widget $widget
     * @return boolean true if available
     */
    public function isAvailableForWidget(Node $node, Widget $widget) {
        return true;
    }

}