<?php

namespace ride\web\cms\node\dispatcher;

use ride\library\cache\pool\CachePool;
use ride\library\mvc\Request;
use ride\library\mvc\Response;
use ride\library\security\model\User;

/**
 * Dispatcher for the frontend of a node
 */
interface NodeDispatcher {

    /**
     * Get the node which is to be dispatched
     * @return \ride\library\cms\node\Node
     */
    public function getNode();

    /**
     * Gets the view for the node
     * @return \ride\web\cms\view\NodeTemplateView
     */
    public function getView();

    /**
     * Dispatches the node
     * @param \ride\library\mvc\Request $request
     * @param \ride\library\mvc\Response $response
     * @param \ride\library\security\model\User $user
     * @param \ride\library\cache\pool\CachePool $cache
     * @return array Array with the region name as key and a view array as
     * value. The view array has the widget id as key and the dispatched
     * widget view as value
     */
    public function dispatch(Request $request, Response $response, User $user = null, CachePool $cache = null);

}
