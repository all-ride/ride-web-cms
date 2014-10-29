<?php

namespace ride\web\cms\node\tree;

use ride\library\cms\node\Node;

/**
 * Node used to render a tree
 */
class TreeNode {

    /**
     * Instance of the node
     * @var \ride\library\cms\node\Node
     */
    protected $node;

    /**
     * Code of the current locale
     * @var string
     */
    protected $locale;

    /**
     * Default URL for the node
     * @var string
     */
    protected $url;

    /**
     * Children of the node
     * @var array
     */
    protected $children;

    /**
     * Actions for the node
     * @var array
     */
    protected $actions;

    /**
     * Flag to see if this node is selected
     * @var boolean
     */
    protected $isSelected;

    /**
     * Flag to see if this node is collapsed
     * @var boolean
     */
    protected $isCollapsed;

    /**
     * Flag to see if this is the home page
     * @var boolean
     */
    protected $isHomePage;

    /**
     * Flag to see if this node is hidden
     * @var boolean
     */
    protected $isHidden;

    /**
     * Constructs a new tree node
     * @param \ride\library\cms\node\Node $node
     * @param string $locale
     * @param string $url
     * @return null
     */
    public function __construct(Node $node, $locale, $url) {
        $this->node = $node;
        $this->locale = $locale;
        $this->url = $url;
        $this->children = array();
        $this->actions = array();

        $this->isSelected = false;
        $this->isCollapsed = false;
        $this->isHomePage = false;
        $this->isHidden = false;
    }

    /**
     * Gets the original node of this tree node
     * @return \ride\library\cms\noe\Node
     */
    public function getNode() {
        return $this->node;
    }

    /**
     * Sets the children for this node
     * @param array $children Array with TreeNode instances
     * @return null
     */
    public function setChildren(array $children) {
        $this->children = $children;
    }

    /**
     * Gets the children of this node
     * @return array
     */
    public function getChildren() {
        return $this->children;
    }

    /**
     * Sets whether this node is selected
     * @param boolean $isSelected
     * @return null
     */
    public function setIsSelected($isSelected) {
        $this->isSelected = $isSelected;
    }

    /**
     * Gets whether this node is selected
     * @return boolean
     */
    public function isSelected() {
        return $this->isSelected;
    }

    /**
     * Sets whether this node is collapsed
     * @param boolean $isCollapsed
     * @return null
     */

    public function setIsCollapsed($isCollapsed) {
        $this->isCollapsed = $isCollapsed;
    }

    /**
     * Gets whether this node is collapsed
     * @return boolean
     */
    public function isCollapsed() {
        return $this->isCollapsed;
    }

    /**
     * Sets whether this node is the home page
     * @param boolean $isHomePage
     * @return null
     */
    public function setIsHomePage($isHomePage) {
        $this->isHomePage = $isHomePage;
    }

    /**
     * Gets whether this node is the home page
     * @return boolean
     */
    public function isHomePage() {
        return $this->isHomePage;
    }

    /**
     * Sets whether this node is hidden
     * @param boolean $isHidden
     * @return null
     */
    public function setIsHidden($isHidden) {
        $this->isHidden = $isHidden;
    }

    /**
     * Gets wheter this node is hidden
     * @return boolean
     */
    public function isHidden() {
        return $this->isHidden;
    }

    /**
     * Checks whether the node is localized
     * @param string $locale
     * @return boolean
     */
    public function isLocalized($locale) {
        $localizedName = $this->node->getProperty(Node::PROPERTY_NAME . '.' . $locale);

        return $localizedName ? true : false;
    }

    /**
     * Gets the default URL for the node
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * Sets the node actions for this node
     * @param array $actions Array with the machine name of the action as key
     * and the URL as value
     * @return null
     */
    public function setActions(array $actions) {
        $this->actions = $actions;
    }

    /**
     * Gets the node actions for this node
     * @return array Array with the machine name of the action as key and the
     * URL as value
     */
    public function getActions() {
        return $this->actions;
    }

}
