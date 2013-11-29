<?php

namespace pallo\web\cms\controller\backend;

use pallo\library\cms\node\exception\NodeNotFoundException;
use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;
use pallo\library\cms\node\SiteNode;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\http\Response;
use pallo\library\i18n\I18n;
use pallo\library\validation\exception\ValidationException;

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
            'multiselect' => true,
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

                $nodeModel->setNode($node);

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

        $this->setTemplateView('cms/backend/folder.form', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $locales,
        ));
    }

}