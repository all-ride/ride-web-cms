<?php

namespace pallo\web\cms\controller\backend;

use pallo\library\cms\node\NodeModel;
use pallo\library\http\Response;
use pallo\library\i18n\I18n;

use pallo\web\cms\controller\backend\action\node\LayoutNodeAction;
use pallo\web\cms\controller\backend\action\node\NodeActionManager;

/**
 * Controller for generic node actions
 */
class NodeController extends AbstractBackendController {

    /**
     * Action to go to the previous type of action for the provided node
     * @param pallo\web\cms\node\action\NodeActionManager $nodeActionManager
     * @param string $locale
     * @param pallo\library\cms\node\NodeModel $nodeModel
     * @param string $site
     * @param string $node
     * @return null
     */
    public function defaultAction(NodeActionManager $nodeActionManager, $locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $nodeTypeManager = $nodeModel->getNodeTypeManager();
        $nodeType = $nodeTypeManager->getNodeType($node->getType());

        $urlVars = array(
            'site' => $node->getRootNodeId(),
            'node' => $node->getId(),
            'locale' => $locale,
        );
        $redirectUrl = null;

        $action = $this->getLastAction(LayoutNodeAction::NAME);
        if ($action == 'edit') {
            $redirectUrl = $this->getUrl($nodeType->getRouteEdit(), $urlVars);
        } else {
            $nodeAction = $nodeActionManager->getNodeAction($action);
            if ($nodeAction->isAvailableForNode($node)) {
                $redirectUrl = $this->getUrl($nodeAction->getRoute(), $urlVars);
            } else {
                $redirectUrl = $this->getUrl($nodeType->getRouteEdit(), $urlVars);
            }
        }

        $this->response->setRedirect($redirectUrl);
    }

    /**
     * Action to clone a node
     * @param pallo\library\i18n\I18n $i18n
     * @param string $locale
     * @param pallo\library\cms\node\NodeModel $nodeModel
     * @param string $site
     * @param string $node
     * @return null
     */
    public function cloneAction(I18n $i18n, $locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail.locale', array(
                'locale' => $locale,
                'site' => $site->getId(),
            ));
        }

        if ($this->request->isPost() || $this->request->isDelete()) {
            $clone = $nodeModel->cloneNode($node);

            $this->addSuccess('success.node.cloned', array(
                'node' => $node->getName($locale),
            ));

            $nodeType = $nodeModel->getNodeTypeManager()->getNodeType($clone->getType());

            $this->response->setRedirect($this->getUrl(
            	$nodeType->getRouteEdit(), array(
                    'locale' => $locale,
                    'site' => $site->getId(),
            	    'node' => $clone->getId(),
                )
        	));

            return;
        }

        $this->setTemplateView('cms/backend/confirm.form', array(
            'type' => 'clone',
            'referer' => $referer,
            'site' => $site,
            'node' => $node,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

    /**
     * Action to delete a node
     * @param pallo\library\i18n\I18n $i18n
     * @param string $locale
     * @param pallo\library\cms\node\NodeModel $nodeModel
     * @param string $site
     * @param string $node
     * @return null
     */
    public function deleteAction(I18n $i18n, $locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail.locale', array(
                'locale' => $locale,
                'site' => $site->getId(),
            ));
        }

        if ($this->request->isPost() || $this->request->isDelete()) {
            $nodeModel->removeNode($node);

            $this->addSuccess('success.node.deleted', array(
                'node' => $node->getName($locale),
            ));

            $this->response->setRedirect($this->getUrl(
            	'cms.site.detail', array(
                    'locale' => $locale,
                    'site' => $site->getId(),
                )
        	));

            return;
        }

        $this->setTemplateView('cms/backend/confirm.form', array(
            'type' => 'delete',
            'referer' => $referer,
            'site' => $site,
            'node' => $node,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

}