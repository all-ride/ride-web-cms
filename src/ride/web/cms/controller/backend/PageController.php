<?php

namespace ride\web\cms\controller\backend;

use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\exception\NodeNotFoundException;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\SiteNode;
use ride\library\cms\theme\ThemeModel;
use ride\library\http\Response;
use ride\library\i18n\translator\Translator;
use ride\library\i18n\I18n;
use ride\library\image\ImageUrlGenerator;
use ride\library\validation\exception\ValidationException;

class PageController extends AbstractNodeTypeController {

    public function formAction(I18n $i18n, $locale, ImageUrlGenerator $imageUrlGenerator, LayoutModel $layoutModel, ThemeModel $themeModel, NodeModel $nodeModel, $site, $node = null) {
        $themes = $themeModel->getThemes();
        $layouts = $layoutModel->getLayouts();
        $locales = $i18n->getLocaleCodeList();
        $translator = $i18n->getTranslator();

        if ($node) {
            if (!$this->resolveNode($nodeModel, $site, $node, 'page')) {
                return;
            }

            $this->setLastAction('edit');
        } else {
            if (!$this->resolveNode($nodeModel, $site)) {
                return;
            }

            $node = $nodeModel->createNode('page');
            $node->setParentNode($site);
        }

        // gather data
        $data = array(
            'name' => $node->getName($locale),
            'route' => $node->getRoute($locale, false),
            'layout' => $node->getLayout($locale),
            'theme' => $this->getThemeValueFromNode($node),
            'availableLocales' => $this->getLocalesValueFromNode($node),
        );

        // build form
        $form = $this->createFormBuilder($data);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.page'),
            'description' => $translator->translate('label.page.description'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('route', 'string', array(
            'label' => $translator->translate('label.route'),
            'description' => $translator->translate('label.route.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('theme', 'select', array(
            'label' => $translator->translate('label.theme'),
            'description' => $translator->translate('label.theme.description'),
            'options' => $this->getThemeOptions($node, $translator, $themes),
        ));
        $form->addRow('layout', 'option', array(
            'label' => $translator->translate('label.layout'),
            'description' => $translator->translate('label.layout.description'),
            'options' => $this->getLayoutOptions($imageUrlGenerator, $translator, $layouts),
            'validators' => array(
                'required' => array(),
            )
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
                $node->setLayout($locale, $data['layout']);
                $node->setTheme($this->getOptionValueFromForm($data['theme']));
                $node->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));

                $nodeModel->setNode($node);

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.page.edit', array(
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

        if (!$layouts) {
            $this->addWarning('warning.layouts.none');
        }

        // show view
        $this->setTemplateView('cms/backend/page.form', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $locales,
        ));
    }

    /**
     * Gets the available layout options
     * @param ride\library\i18n\translator\Translator $translator
     * @param array $layouts
     * @return array Array with the layout machine name as key and the
     * translation as value
     */
    protected function getLayoutOptions(ImageUrlGenerator $imageUrlGenerator, Translator $translator, array $layouts) {
        $options = array();

        foreach ($layouts as $layout => $null) {
            $options[$layout] = '<img src="' . $imageUrlGenerator->generateUrl('img/cms/layout/' . $layout . '.png') . '" alt="' . $layout . '" title="' . $translator->translate('layout.' . $layout) . '" />';
        }

        return $options;
    }

}