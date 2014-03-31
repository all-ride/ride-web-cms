<?php

namespace ride\web\cms\controller\frontend;

use ride\library\cms\node\exception\NodeNotFoundException;
use ride\library\cms\node\NodeModel;
use ride\library\http\Response;

/**
 * Controller for the frontend of the expired routes
 */
class ExpiredController extends AbstractController {

	/**
     * Dispatches the frontend of a expired route
     * @param \ride\library\cms\node\NodeModel $nodeModel Instance of the node
     * model
     * @param string $node Id of the node
     * @param mixed $path Argument to see if additional arguments are provided
     * @return null
	 */
	public function indexAction(NodeModel $nodeModel, $node) {
	    try {
	        $node = $nodeModel->getNode($node);
	    } catch (NodeNotFoundException $exception) {
	        $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

	        return;
	    }

	    $locale = $this->getLocale();

	    $redirectUrl = $this->request->getBaseScript() . $node->getRoute($locale);

	    if (func_num_args() > 2) {
	        $arguments = func_get_args();
	        array_shift($arguments);
	        array_shift($arguments);

	        $redirectUrl .= '/' . implode('/', $arguments);
	    }

	    $this->response->setRedirect($redirectUrl, Response::STATUS_CODE_MOVED_PERMANENTLY);
	}

}
