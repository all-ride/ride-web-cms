<?php

namespace ride\web\cms\controller\backend;

use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

class RedirectController extends AbstractNodeTypeController {

    public function formAction(Cms $cms, $site, $revision = null, $node = null) {
        if ($node) {
            if (!$cms->resolveNode($site, $revision, $node, 'redirect')) {
                return;
            }

            $cms->setLastAction('edit');
        } else {
            if (!$this->resolveNode($nodeModel, $site, $revision)) {
                return;
            }

            $node = $cms->createNode('redirect', $site);
        }

        $translator = $this->getTranslator();
        $locales = $cms->getLocales();

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail.locale', array(
                'site' => $site->getId(),
                'revision' => $site->getRevision(),
                'locale' => $locale,
            ));
        }

        // get available nodes
        $nodeList = $cms->getNodeList($site, $locale, true, false);
        if ($node && isset($nodeList[$node->getId()])) {
            unset($nodeList[$node->getId()]);
        }

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

        if ($site->isLocalizationMethodCopy()) {
            $form->addRow('availableLocales', 'select', array(
                'label' => $translator->translate('label.locales'),
                'description' => $translator->translate('label.locales.available.description'),
                'options' => $this->getLocalesOptions($node, $translator, $locales),
                'multiple' => true,
                'validators' => array(
                    'required' => array(),
                )
            ));
        }

        // process form
        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setName($locale, $data['name']);
                $node->setRoute($locale, $data['route']);
                if ($site->isLocalizationMethodCopy()) {
                    $node->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));
                } else {
                    $node->setAvailableLocales($locale);
                }

                if ($data['redirect-type'] == 'node') {
                    $node->setRedirectNode($locale, $data['redirect-node']);
                    $node->setRedirectUrl($locale, null);
                } else {
                    $node->setRedirectNode($locale, null);
                    $node->setRedirectUrl($locale, $data['redirect-url']);
                }

                $cms->saveNode($node, (!$node->getId() ? 'Created new redirect ' : 'Updated redirect ') . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale),
                ));

                $url = $this->getUrl('cms.redirect.edit', array(
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
