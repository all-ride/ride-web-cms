<?php

namespace ride\web\cms\controller\backend\action\widget;

use ride\library\cms\node\Node;
use ride\library\cms\widget\Widget;
use ride\library\mvc\controller\Controller;

/**
 * Interface of a widget action
 */
interface WidgetAction extends Controller {

    /**
     * Get the machine name of this action
     * @return string
     */
    public function getName();

    /**
     * Get the route of this action
     * @return string
     */
    public function getRoute();

    /**
     * Checks if this action is available for the widget
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\cms\widget\Widget $widget
     * @return boolean true if available
     */
    public function isAvailableForWidget(Node $node, Widget $widget);

}