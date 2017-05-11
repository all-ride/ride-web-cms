<?php

namespace ride\web\cms\controller\frontend;

use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\router\Route;
use ride\library\StringHelper;

use ride\web\cms\node\type\HomeNodeType;
use ride\web\cms\Cms;
use ride\web\WebApplication;

/**
 * Controller for the frontend of the home nodes
 */
class HomeController extends AbstractController {

    /**
     * Dispatches the frontend of a home node
     * @param integer $node Id of the node
     * @retur&n null
     */
    public function indexAction(WebApplication $web, Cms $cms, I18n $i18n, $site, $node, $locale = null) {
        if (!$cms->resolveNode($site, null, $node, HomeNodeType::NAME)) {
            return;
        }

        if ($locale === null) {
            $locale = $i18n->getLocale()->getCode();
        } else {
            $i18n->setCurrentLocale($locale);
        }

        // chain request if we are not literally on /
        $path = $this->request->getBasePath(true);
        if (!$path || ($path !== $node->getRoute($locale) && $path != '/')) {
            return $this->chainWebRequest();
        }

        // morf into the real home page
        $node = $node->getHomePage($cms->getNodeModel(), $locale);
        if (!$node) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        // chain a request to the frontend callback
        $nodeType = $cms->getNodeType($node);

        $callback = $nodeType->getFrontendCallback();

        $route = new Route($node->getRoute($locale), $callback);
        $route->setIsDynamic(true);
        $route->setPredefinedArguments(array(
            'site' => $site->getId(),
            'node' => $node->getId(),
            'locale' => $locale,
        ));

        $basePath = $this->request->getBasePath(true);
        if (StringHelper::startsWith($basePath, $path)) {
            $basePath = substr($basePath, strlen($path));
        }

        $arguments = ltrim($basePath, '/');
        if ($arguments) {
            $route->setArguments(explode('/', $arguments));
        }

        $request = $web->createRequest($route->getPath(), 'GET');
        $request->setRoute($route);

        $this->response->setStatusCode(Response::STATUS_CODE_OK);

        return $request;
    }

}
