<?php

namespace ride\web\cms\controller\backend;

use ride\web\cms\controller\backend\action\node\LayoutNodeAction;
use ride\web\cms\controller\backend\action\node\NodeActionManager;
use ride\web\cms\Cms;

use ride\web\base\controller\AbstractController;

/**
 * Controller for generic node actions
 */
class NodeController extends AbstractController {

    /**
     * Action to go to the previous type of action for the provided node
     * @param \ride\web\cms\Cms $cms
     * @param \ride\web\cms\controller\backend\action\node\NodeActionManager $nodeActionManager
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @return null
     */
    public function defaultAction(Cms $cms, NodeActionManager $nodeActionManager, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $nodeType = $cms->getNodeType($node);

        $urlVars = array(
            'site' => $node->getRootNodeId(),
            'revision' => $node->getRevision(),
            'node' => $node->getId(),
            'locale' => $locale,
        );
        $redirectUrl = null;

        $action = $cms->getLastAction(LayoutNodeAction::NAME);
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

        $referer = $this->request->getQueryParameter('referer');
        if ($referer) {
            $referer = '?referer=' . urlencode($referer);
        }

        $this->response->setRedirect($redirectUrl . $referer);
    }

    /**
     * Action to clone a node
     * @param \ride\web\cms\Cms $cms
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @return null
     */
    public function cloneAction(Cms $cms, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail.locale', array(
                'site' => $site->getId(),
                'revision' => $site->getRevision(),
                'locale' => $locale,
            ));
        }

        if ($this->request->isPost()) {
            $clone = $cms->cloneNode($node);

            $this->addSuccess('success.node.cloned', array(
                'node' => $node->getName($locale),
            ));

            $nodeType = $cms->getNodeType($clone);

            $url = $this->getUrl($nodeType->getRouteEdit(), array(
                'site' => $site->getId(),
                'revision' => $clone->getRevision(),
                'locale' => $locale,
                'node' => $clone->getId(),
            ));
            if ($referer) {
                $url .= '?referer=' . urlencode($referer);
            }

            $this->response->setRedirect($url);

            return;
        }

        $this->setTemplateView('cms/backend/confirm.form', array(
            'type' => 'clone',
            'referer' => $referer,
            'site' => $site,
            'node' => $node,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

    /**
     * Action to delete a node
     * @param \ride\web\cms\Cms $cms
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @return null
     */
    public function deleteAction(Cms $cms, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail.locale', array(
                'locale' => $locale,
                'site' => $site->getId(),
                'revision' => $site->getRevision(),
            ));
        }

        $form = $this->createFormBuilder();
        $form->addRow('recursive', 'option', array(
            'label' => '',
            'description' => $translator->translate('label.confirm.node.delete.recursive'),
        ));
        $form = $form->build();

        if ($form->isSubmitted()) {
            $data = $form->getData();

            $cms->removeNode($node, $data['recursive']);

            $this->addSuccess('success.node.deleted', array(
                'node' => $node->getName($locale),
            ));

            $url = $this->getUrl('cms.site.detail.locale', array(
                'site' => $site->getId(),
                'revision' => $node->getRevision(),
                'locale' => $locale,
                'node' => $node->getId(),
            ));
            if ($referer) {
                $url .= '?referer=' . urlencode($referer);
            }

            $this->response->setRedirect($url);

            return;
        }

        $this->setTemplateView('cms/backend/delete.form', array(
            'form' => $form->getView(),
            'referer' => $referer,
            'site' => $site,
            'node' => $node,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

    /**
     * Action to store the collapse status of a node
     * @param \ride\web\cms\Cms $cms
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @return null
     */
    public function collapseAction(Cms $cms, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $cms->collapseNode($node);
    }

}
