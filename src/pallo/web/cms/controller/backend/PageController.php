<?php

namespace pallo\web\cms\controller\backend;

use pallo\library\cms\expired\ExpiredRouteModel;
use pallo\library\cms\layout\LayoutModel;
use pallo\library\cms\node\exception\NodeNotFoundException;
use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;
use pallo\library\cms\node\SiteNode;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\http\Response;
use pallo\library\i18n\translator\Translator;
use pallo\library\i18n\I18n;
use pallo\library\image\ImageUrlGenerator;
use pallo\library\validation\exception\ValidationException;

class PageController extends AbstractNodeTypeController {

    public function formAction(I18n $i18n, $locale, ImageUrlGenerator $imageUrlGenerator, LayoutModel $layoutModel, ThemeModel $themeModel, ExpiredRouteModel $expiredRouteModel, NodeModel $nodeModel, $site, $node = null) {
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
            'multiselect' => true,
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

                $oldRoute = $data['route'];

                $data = $form->getData();

                $node->setName($locale, $data['name']);
                $node->setRoute($locale, $data['route']);
                $node->setLayout($locale, $data['layout']);
                $node->setTheme($this->getOptionValueFromForm($data['theme']));
                $node->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));

                $nodeModel->setNode($node);

                if ($oldRoute) {
                    $newRoute = $node->getRoute($locale, false);
                    if ($newRoute && $oldRoute != $newRoute) {
                        $baseUrl = $site->getBaseUrl($locale);

                        $expiredRouteModel->addExpiredRoute($node->getId(), $locale, $oldRoute, $baseUrl);
                    }
                }

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

        // show view
        $this->setTemplateView('cms/backend/page.form', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $locales,
        ));
    }


    /**
     * Gets the available layout options
     * @param pallo\library\i18n\translator\Translator $translator
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