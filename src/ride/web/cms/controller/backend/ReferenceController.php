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

        $name = $node->getName($locale);
        if ($node->getNode() && $node->getNode()->getName($locale) == $name) {
            $name = '';
        }

        // gather data
        $data = array(
            'name' => $name,
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
                $node->setReferenceNode($data['reference-node']);
                if (!$node->getNode()) {
                    $node->setNode($cms->getNode($site->getId(), $revision, $data['reference-node']));
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

}
