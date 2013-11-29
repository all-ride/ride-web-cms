<?php

namespace pallo\web\cms\node;

use pallo\library\cms\node\Node;

/**
 * Interface to generate the node tree of the backend
 */
interface NodeTreeGenerator {

    /**
     * Renders the HTML for the node tree
     * @return string
     */
    public function getTreeHtml(Node $node, $locale);

}