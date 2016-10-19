<?php

namespace ride\web\cms\controller\backend;

use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

class ReferenceController extends AbstractNodeTypeController {

    public function formAction(Cms $cms, $locale, $site, $revision = null, $node = null) {
        if ($node) {
            if (!$cms->resolveNode($site, $revision, $node, 'reference')) {
                return;
            }

            $cms->setLastAction('edit');
        } else {
            if (!$cms->resolveNode($site, $revision)) {
                return;
            }

            $node = $cms->createNode('reference', $site);
        }

        $this->setContentLocale($locale);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        // get available nodes
        $nodeList = $cms->getNodeList($site, $locale, true, false);
        if ($node && isset($nodeList[$node->getId()])) {
            unset($nodeList[$node->getId()]);
        }

        // gather data
        $data = array(
            'name' => $this->getName($node, $locale),
            'name-title' => $this->getName($node, $locale, 'title'),
            'name-menu' => $this->getName($node, $locale, 'menu'),
            'name-breadcrumb' => $this->getName($node, $locale, 'breadcrumb'),
            'reference-node' => $node->getReferenceNode(),
        );

        // build form
        $form = $this->createFormBuilder($data);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.reference'),
            'description' => $translator->translate('label.reference.name.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('name-title', 'string', array(
            'label' => $translator->translate('label.name.title'),
            'description' => $translator->translate('label.name.title.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('name-menu', 'string', array(
            'label' => $translator->translate('label.name.menu'),
            'description' => $translator->translate('label.name.menu.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('name-breadcrumb', 'string', array(
            'label' => $translator->translate('label.name.breadcrumb'),
            'description' => $translator->translate('label.name.breadcrumb.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('reference-node', 'select', array(
            'label' => $translator->translate('label.reference.node'),
            'description' => $translator->translate('label.reference.node.description'),
            'options' => $nodeList,
            'validators' => array(
                'required' => array(),
            ),
        ));

        // process form
        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setName($locale, $data['name']);
                $node->setName($locale, $data['name-title'] ? $data['name-title'] : null, 'title');
                $node->setName($locale, $data['name-menu'] ? $data['name-menu'] : null, 'menu');
                $node->setName($locale, $data['name-breadcrumb'] ? $data['name-breadcrumb'] : null, 'breadcrumb');
                $node->setReferenceNode($data['reference-node']);
                if (!$node->getNode()) {
                    $node->setNode($cms->getNode($site->getId(), $revision, $data['reference-node']));
                }

                if (!$site->isLocalizationMethodCopy()) {
                    $node->setAvailableLocales($locale);
                }

                $cms->saveNode($node, (!$node->getId() ? 'Created new reference ' : 'Updated reference ') . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale),
                ));

                $url = $this->getUrl('cms.reference.edit', array(
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
        $this->setTemplateView('cms/backend/reference.form', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

    private function getName($node, $locale, $context = null) {
        $name = $node->getName($locale, $context);
        if ($node->getNode() && $node->getNode()->getName($locale, $context) == $name) {
            $name = null;
        }

        return $name;
    }

}
