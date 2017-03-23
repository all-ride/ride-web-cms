<?php

namespace ride\web\cms\node\type;

use ride\library\cms\node\type\HomeNodeType as LibraryHomeNodeType;

/**
 * Frontend implementation for a home node type
 */
class HomeNodeType extends LibraryHomeNodeType implements NodeType {

    /**
     * Gets the callback for the frontend route
     * @return string|array|\ride\web\cms\controller\frontend\HomeController
     */
    public function getFrontendCallback() {
        return array('ride\\web\\cms\\controller\\frontend\\HomeController', 'indexAction');
    }

    /**
     * Gets the id of the route to create a new node of this type
     * @return string Route id
     */
    public function getRouteAdd() {
        return 'cms.home.add';
    }

    /**
     * Gets the id of the route to edit a node of this type
     * @return string Route id
     */
    public function getRouteEdit() {
        return 'cms.home.edit';
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
