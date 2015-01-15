<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\http\Response;
use ride\library\template\TemplateFacade;

use ride\web\cms\node\dispatcher\NodeDispatcherFactory;
use ride\web\cms\Cms;

/**
 * Controller of the preview node action
 */
class PreviewNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'preview';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.preview';

    /**
     * Instance of the CMS facade
     * @var \ride\web\cms\Cms
     */
    protected $cms;

    /**
     * Constructs a new go action
     * @param \ride\web\cms\Cms $cms
     */
    public function __construct(Cms $cms) {
        $this->cms = $cms;
    }

    /**
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        if (!$node->getParent()) {
            return !$node->isAutoPublish();
        }

        $nodeType = $this->cms->getNodeType($node);

        $isAvailable = $nodeType->getFrontendCallback() ? true : false;

        return $isAvailable && !$node->getRootNode()->isAutoPublish();
    }

    /**
     * Perform the preview node action
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $locale Code of the locale
     * @return null
     */
    public function indexAction($site, $revision, $node, $locale) {
        if (!$this->cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $url = $this->getUrl('cms.site.preview', array(
            'site' => $site->getId(),
            'revision' => $revision,
            'locale' => $locale,
        )) . $node->getRoute($locale);

        $this->response->setRedirect($url);
    }

    /**
     * Dynamic action to preview a node
     * @param \ride\web\cms\node\dispatcher\NodeDispatcherFactory $nodeDispatcherFactory
     * @param \ride\library\template\TemplateFacade $templateFacade
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $locale Code of the locale
     * @return null
     */
    public function previewAction(NodeDispatcherFactory $nodeDispatcherFactory, TemplateFacade $templateFacade, $site, $revision, $locale) {
        $node = $site;

        if (!$this->cms->resolveNode($site, $revision, $node, null, true)) {
            return;
        }

        $cache = null;
        $i18n = $this->getI18n();
        $i18n->setCurrentLocale($locale);

        $requestUrl = $this->request->getUrl();
        $routeUrl = $this->request->getRoute()->getUrl($this->request->getBaseScript(), array(
            'locale' => $locale,
            'site' => $site->getId(),
            'revision' => $revision,
        ));

        $path = str_replace($routeUrl, '', $requestUrl);

        $node = $site->getChildByRoute($path, $locale, $this->cms->getLocales());
        if (!$node) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $nodeDispatcher = $nodeDispatcherFactory->createNodeDispatcher($site, $node->getId(), $routeUrl, $locale);
        if (!$nodeDispatcher) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $node = $nodeDispatcher->getNode();
        if (!$node->isAvailableInLocale($locale)) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $nodeView = $nodeDispatcher->getView();
        $nodeView->setLayouts($this->cms->getLayouts());
        $nodeView->setTemplateFacade($templateFacade);

        $templateFacade->setThemeModel($this->cms->getThemeModel());
        $templateFacade->setDefaultTheme($nodeView->getTemplate()->getTheme());

        $this->request->setBaseScript($routeUrl);
        $textParser = $this->dependencyInjector->get('ride\\library\\cms\\content\\text\\TextParser', 'chain');
        $textParser->setBaseUrl($this->request->getBaseUrl());
        $textParser->setSiteUrl($routeUrl);

        $nodeDispatcher->dispatch($this->request, $this->response, $this->getUser(), $cache);
    }

}
