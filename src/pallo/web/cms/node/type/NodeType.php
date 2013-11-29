<?php

namespace pallo\web\cms\node\type;

/**
 * Interface for a node type
 */
interface NodeType {

    /**
     * Gets the id of the route to create a new node of this type
     * @return string Route id
     */
    public function getRouteAdd();

    /**
     * Gets the id of the route to edit a node of this type
     * @return string Route id
     */
    public function getRouteEdit();

    /**
     * Gets the id of the route to clone a node of this type
     * @return string Route id
     */
    public function getRouteClone();

    /**
     * Gets the id of the route to delete a node of this type
     * @return string Route id
     */
    public function getRouteDelete();

}