<?php

namespace pallo\web\cms\controller\backend\action\widget;

use pallo\library\cms\node\Node;
use pallo\library\cms\widget\Widget;
use pallo\library\mvc\controller\Controller;

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
     * @param pallo\library\cms\node\Node $node
     * @param pallo\library\cms\widget\Widget $widget
     * @return boolean true if available
     */
    public function isAvailableForWidget(Node $node, Widget $widget);

}