<?php

namespace ride\web\cms\controller\frontend;

use ride\library\http\Response;
use ride\library\router\Route;

use ride\web\base\controller\AbstractController as BaseAbstractController;

/**
 * Abstract controller of the CMS frontend
 */
class AbstractController extends BaseAbstractController {

    /**
     * Chains the current request to the public web controller
     * @return null|\ride\library\mvc\Request
     */
    protected function chainWebRequest() {
        // not found, try the public controller
        $arguments = ltrim($this->request->getBasePath(true), '/');
        if (!$arguments) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $controller = $this->dependencyInjector->get('ride\\library\\mvc\\controller\\Controller', 'public');
        $callback = array($controller, 'indexAction');

        $route->setIsDynamic(true);
        $route->setArguments(explode('/', $arguments));

        $this->request->setRoute($route);
        $this->response->setStatusCode(Response::STATUS_CODE_OK);

        return $this->request;
    }

}
