<?php

namespace ride\web\cms\controller\backend;

use ride\library\cms\node\NodeModel;
use ride\library\cms\theme\ThemeModel;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\validation\exception\ValidationException;

class FolderController extends AbstractNodeTypeController {

    public function formAction(I18n $i18n, $locale, ThemeModel $themeModel, NodeModel $nodeModel, $site, $node = null) {
        $themes = $themeModel->getThemes();
        $locales = $i18n->getLocaleCodeList();
        $translator = $i18n->getTranslator();

        if ($node) {
            if (!$this->resolveNode($nodeModel, $site, $node, 'folder')) {
                return;
            }

            $this->setLastAction('edit');
        } else {
            if (!$this->resolveNode($nodeModel, $site)) {
                return;
            }

            $node = $nodeModel->createNode('folder');
            $node->setParentNode($site);
        }

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

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setName($locale, $data['name']);
                $node->setTheme($this->getOptionValueFromForm($data['theme']));
                $node->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));

                $nodeModel->setNode($node, (!$node->getId() ? 'Created new folder ' : 'Updated folder ') . $node->getName());

                $this->addSuccess('success.node.saved', array(
                	'node' => $node->getName($locale),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.folder.edit', array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                        'node' => $node->getId(),
                    )
                ));

                return;
            } catch (ValidationException $exception) {
                $form->setValidationException($exception);

                $this->addError('error.validation');

                $this->response->setStatusCode(Response::STATUS_CODE_UNPROCESSABLE_ENTITY);
            }
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail.locale', array(
            	'site' => $site->getId(),
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
