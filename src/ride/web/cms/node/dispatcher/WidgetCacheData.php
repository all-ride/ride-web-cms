<?php

namespace ride\web\cms\node\dispatcher;

use ride\library\mvc\view\View;

/**
 * Data container for the view of a widget
 */
class WidgetCacheData {

    /**
     * View to cache
     * @var \ride\library\mvc\view\View
     */
    private $view;

    /**
     * Page context
     * @var array
     */
    private $context;

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
     * Flag to see if the view is the section
     * @var boolean
     */
    private $isSection;

    /**
     * Flag to see if the view is the block
     * @var boolean
     */
    private $isBlock;

    /**
     * Flag to see if this widget matched the route arguments
     * @var boolean
     */
    private $routesMatched;

    /**
     * Constructs a new data container
     * @param array $context Context variables of the widget
     * @param boolean $isContent Flag to see if this view is the page
     * @param boolean $isRegion Flag to see if this view is the region
     * @param boolean $routesMatched Flag to see if this widget matched the
     * route arguments
     * @return null
     */
    public function __construct(array $context = array(), $isContent = false, $isRegion = false, $isSection = false, $isBlock = false, $routesMatched = false) {
        $this->context = $context;
        $this->isContent = $isContent;
        $this->isRegion = $isRegion;
        $this->isSection = $isSection;
        $this->isBlock = $isBlock;
        $this->routesMatched = $routesMatched;
    }

    /**
     * Sets the view to cache
     * @param \ride\library\mvc\view\View $view View to cache
     * @return null
     */
    public function setView(View $view) {
        $this->view = $view;
    }

    /**
     * Gets the view of this widget
     * @return \ride\library\mvc\view\View
     */
    public function getView() {
        return $this->view;
    }

    /**
     * Gets the context variables of the widget
     * @return array
     */
    public function getContext() {
        return $this->context;
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
     * Gets whether this view is the section
     * @return boolean
     */
    public function isSection() {
        return $this->isSection;
    }

    /**
     * Gets whether this view is the block
     * @return boolean
     */
    public function isBlock() {
        return $this->isBlock;
    }

    /**
     * Gets whether this widget mathed the route arguments
     * @return boolean
     */
    public function areRoutesMatched() {
        return $this->routesMatched;
    }

}
