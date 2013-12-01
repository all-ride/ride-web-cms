<?php

namespace pallo\web\cms\controller\frontend;

use pallo\library\cms\node\exception\NodeNotFoundException;
use pallo\library\cms\node\NodeModel;
use pallo\library\http\Response;

use pallo\web\base\controller\AbstractController;

/**
 * Controller for the frontend of the expired routes
 */
class ExpiredController extends AbstractController {

	/**
     * Dispatches the frontend of a expired route
     * @param pallo\library\cms\node\NodeModel $nodeModel Instance of the node
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