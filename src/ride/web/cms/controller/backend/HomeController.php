<?php

namespace ride\web\cms\controller\backend;

use ride\library\validation\exception\ValidationException;

use ride\web\cms\form\HomeComponent;
use ride\web\cms\Cms;

class HomeController extends AbstractNodeTypeController {

    public function formAction(Cms $cms, $locale, $site, $revision = null, $node = null) {
        if ($node) {
            if (!$cms->resolveNode($site, $revision, $node, 'home')) {
                return;
            }

            $cms->setLastAction('edit');
        } else {
            if (!$cms->resolveNode($site, $revision)) {
                return;
            }

            $node = $cms->createNode('home', $site);
        }

        $this->setContentLocale($locale);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        // get available nodes
        $nodeList = $cms->getNodeList($site, $locale, false, false);
        if ($node && isset($nodeList[$node->getId()])) {
            unset($nodeList[$node->getId()]);
        }

        // gather data
        $data = array(
            'name' => $node->getName($locale),
            'default-node' => $node->getDefaultNode($locale),
        );

        // build form

        $homeComponent = new HomeComponent();
        $homeComponent->setNodeList($nodeList);

        $form = $this->createFormBuilder($data);
        $form->addRow('default-node', 'option', array(
            'label' => $translator->translate('label.home.node.default'),
            'description' => $translator->translate('label.home.node.default.description'),
            'options' => $nodeList,
            'validators' => array(
                'required' => array(),
            ),
            'widget' => 'select',
        ));
        $form->addRow('nodes', 'collection', array(
            'label' => $translator->translate('label.home.nodes'),
            'type' => 'component',
            'options' => array(
                'component' => $homeComponent,
            ),
        ));

        // process form
        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setName($locale, $this->getTranslator($locale)->translate('label.homepage'));
                $node->setRoute($locale, '/');
                $node->setDefaultNode($locale, $data['default-node']);

                $cms->saveNode($node, (!$node->getId() ? 'Created new homepage ' : 'Updated homepage ') . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale),
                ));

                $url = $this->getUrl('cms.home.edit', array(
                    'locale' => $locale,
                    'site' => $site->getId(),
                    'revision' => $node->getRevision(),
                    'node' => $node->getId(),
                ));

                if ($referer) {
                    $url .= '?referer=' . urlencode($referer);
                }

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        // show view
        $this->setTemplateView('cms/backend/home.form', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
