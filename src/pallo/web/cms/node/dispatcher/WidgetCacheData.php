<?php

namespace pallo\web\cms\node\dispatcher;

use pallo\library\mvc\view\View;

/**
 * Data container for the view of a widget
 */
class WidgetCacheData {

    /**
     * View to cache
     * @var pallo\library\mvc\view\View
     */
    private $view;

    /**
     * Flag to see if the view is the content
     * @var boolean
     */
    private $isContent;

    /**
     * Flag to see if the view is the region
     * @var boolean
     */
    private $isRegion;

    /**
     * Flag to see if this widget matched the route arguments
     * @var boolean
     */
    private $routesMatched;

    /**
     * Constructs a new data container
     * @param pallo\library\mvc\view\View $view View to cache
     * @param boolean $isContent Flag to see if this view is the page
     * @param boolean $isRegion Flag to see if this view is the region
     * @param boolean $routesMatched Flag to see if this widget matched the
     * route arguments
     * @return null
     */
    public function __construct(View $view = null, $isContent = false, $isRegion = false, $routesMatched = false) {
        $this->view = $view;
        $this->isContent = $isContent;
        $this->isRegion = $isRegion;
        $this->routesMatched = $routesMatched;
    }

    /**
     * Gets the view of this widget
     * @return pallo\library\mvc\view\View
     */
    public function getView() {
        return $this->view;
    }

    /**
     * Gets whether this widget is the content
     * @return boolean
     */
    public function isContent() {
        return $this->isContent;
    }

    /**
     * Gets whether this view is the region
     * @return boolean
     */
    public function isRegion() {
        return $this->isRegion;
    }

    /**
     * Gets whether this widget mathed the route arguments
     * @return boolean
     */
    public function areRoutesMatched() {
        return $this->routesMatched;
    }

}