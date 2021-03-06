<?php

namespace ride\web\cms\node\type;

use ride\library\cms\node\type\PageNodeType as LibraryPageNodeType;

/**
 * Frontend implementation for a page node type
 */
class PageNodeType extends LibraryPageNodeType implements NodeType {

    /**
     * Gets the callback for the frontend route
     * @return string|array|\ride\web\cms\controller\frontend\NodeController
     */
    public function getFrontendCallback() {
        return array('ride\\web\\cms\\controller\\frontend\\NodeController', 'indexAction');
    }

    /**
     * Gets the id of the route to create a new node of this type
     * @return string Route id
     */
    public function getRouteAdd() {
        return 'cms.page.add';
    }

    /**
     * Gets the id of the route to edit a node of this type
     * @return string Route id
     */
    public function getRouteEdit() {
        return 'cms.page.edit';
    }

    /**
     * Gets the id of the route to clone a node of this type
     * @return string Route id
     */
    public function getRouteClone() {
        return 'cms.node.clone';
    }

    /**
     * Gets the id of the route to delete a node of this type
     * @return string Route id
     */
    public function getRouteDelete() {
        return 'cms.node.delete';
    }

}
