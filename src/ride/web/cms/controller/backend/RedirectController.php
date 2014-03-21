<?php

namespace ride\web\cms\controller\backend;

use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\exception\NodeNotFoundException;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\SiteNode;
use ride\library\cms\theme\ThemeModel;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\validation\exception\ValidationException;

class RedirectController extends AbstractNodeTypeController {

    public function formAction(I18n $i18n, $locale, NodeModel $nodeModel, $site, $node = null) {
        $locales = $i18n->getLocaleCodeList();
        $translator = $i18n->getTranslator();

        if ($node) {
            if (!$this->resolveNode($nodeModel, $site, $node, 'redirect')) {
                return;
            }

            $this->setLastAction('edit');
        } else {
            if (!$this->resolveNode($nodeModel, $site)) {
                return;
            }

            $node = $nodeModel->createNode('redirect');
            $node->setParentNode($site);
        }

        $rootNode = $nodeModel->getNode($node->getRootNodeId(), null, true);
        $nodeList = $nodeModel->getListFromNodes(array($rootNode), $locale, false);
        $nodeList = array($rootNode->getId() => '/' . $rootNode->getName($locale)) + $nodeList;

        // gather data
        $data = array(
            'name' => $node->getName($locale),
            'redirect-node' => $node->getRedirectNode($locale),
            'redirect-url' => $node->getRedirectUrl($locale),
            'route' => $node->getRoute($locale, false),
            'availableLocales' => $this->getLocalesValueFromNode($node),
        );

        if ($data['redirect-url']) {
            $data['redirect-type'] = 'url';
        } else {
            $data['redirect-type'] = 'node';
        }

        // build form
        $form = $this->createFormBuilder($data);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.redirect'),
            'description' => $translator->translate('label.redirect.name.description'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('redirect-type', 'option', array(
            'label' => $translator->translate('label.redirect.to'),
            'options' => array(
                'node' => $translator->translate('label.node'),
                'url' => $translator->translate('label.url'),
            ),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('redirect-node', 'select', array(
            'label' => $translator->translate('label.redirect.node'),
            'description' => $translator->translate('label.redirect.node.description'),
            'options' => $nodeList,
        ));
        $form->addRow('redirect-url', 'website', array(
            'label' => $translator->translate('label.redirect.url'),
            'description' => $translator->translate('label.redirect.url.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('route', 'string', array(
            'label' => $translator->translate('label.route'),
            'description' => $translator->translate('label.route.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('availableLocales', 'select', array(
            'label' => $translator->translate('label.locales'),
            'description' => $translator->translate('label.locales.available.description'),
            'options' => $this->getLocalesOptions($node, $translator, $locales),
            'multiple' => true,
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->setRequest($this->request);

        // process form
        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setName($locale, $data['name']);
                $node->setRoute($locale, $data['route']);
                $node->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));
                if ($data['redirect-type'] == 'node') {
                    $node->setRedirectNode($locale, $data['redirect-node']);
                    $node->setRedirectUrl($locale, null);
                } else {
                    $node->setRedirectNode($locale, null);
                    $node->setRedirectUrl($locale, $data['redirect-url']);
                }

                $nodeModel->setNode($node, (!$node->getId() ? 'Created new redirect ' : 'Updated redirect ') . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.redirect.edit', array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                        'node' => $node->getId(),
                    )
                ));

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail.locale', array(
                'site' => $site->getId(),
                'locale' => $locale,
            ));
        }

        // show view
        $this->setTemplateView('cms/backend/redirect.form', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $locales,
        ));
    }

}
