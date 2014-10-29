<?php

namespace ride\web\cms\controller\backend;

use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

class FolderController extends AbstractNodeTypeController {

    public function formAction(Cms $cms, $locale, $site, $revision = null, $node = null) {
        if ($node) {
            if (!$cms->resolveNode($site, $revision, $node, 'folder')) {
                return;
            }

            $cms->setLastAction('edit');
        } else {
            if (!$cms->resolveNode($site, $revision)) {
                return;
            }

            $node = $cms->createNode('folder', $site);
        }

        $translator = $this->getTranslator();
        $locales = $cms->getLocales();
        $themes = $cms->getThemes();

        $data = array(
            'name' => $node->getName($locale),
            'theme' => $this->getThemeValueFromNode($node),
            'availableLocales' => $this->getLocalesValueFromNode($node),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.folder'),
            'description' => $translator->translate('label.folder.description'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('theme', 'select', array(
            'label' => $translator->translate('label.theme'),
            'description' => $translator->translate('label.theme.description'),
            'options' => $this->getThemeOptions($node, $translator, $themes),
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

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setName($locale, $data['name']);
                $node->setTheme($this->getOptionValueFromForm($data['theme']));
                if ($site->isLocalizationMethodCopy()) {
                    $node->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));
                } else {
                    $node->setAvailableLocales($locale);
                }

                $cms->saveNode($node, (!$node->getId() ? 'Created new folder ' : 'Updated folder ') . $node->getName());

                $this->addSuccess('success.node.saved', array(
                	'node' => $node->getName($locale),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.folder.edit', array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                        'revision' => $node->getRevision(),
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
            	'revision' => $site->getRevision(),
                'locale' => $locale,
            ));
        }

        $this->setTemplateView('cms/backend/folder.form', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $locales,
        ));
    }

}
