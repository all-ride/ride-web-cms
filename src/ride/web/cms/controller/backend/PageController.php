<?php

namespace ride\web\cms\controller\backend;

use ride\library\image\ImageUrlGenerator;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

class PageController extends AbstractNodeTypeController {

    public function formAction(Cms $cms, $locale, ImageUrlGenerator $imageUrlGenerator, $site, $revision, $node = null) {
        if ($node) {
            if (!$cms->resolveNode($site, $revision, $node, 'page')) {
                return;
            }

            $cms->setLastAction('edit');
        } else {
            if (!$cms->resolveNode($site, $revision)) {
                return;
            }

            $node = $cms->createNode('page', $site);
        }

        $this->setContentLocale($locale);

        $hasThemePermission = $this->getSecurityManager()->isPermissionGranted(Cms::PERMISSION_THEME);

        $translator = $this->getTranslator();
        $themes = $cms->getThemes();

        $referer = $this->request->getQueryParameter('referer');

        // gather data
        $data = array(
            'name' => $node->getName($locale),
            'name-title' => $node->get('name.' . $locale . '.title', null, false),
            'name-menu' => $node->get('name.' . $locale . '.menu', null, false),
            'name-breadcrumb' => $node->get('name.' . $locale . '.breadcrumb', null, false),
            'description' => $node->getDescription($locale),
            'image' => $node->getImage($locale),
            'route' => $node->getRoute($locale, false),
            'theme' => $cms->getThemeValueFromNode($node),
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
        $form->addRow('route', 'string', array(
            'label' => $translator->translate('label.route'),
            'description' => $translator->translate('label.route.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('description', 'text', array(
            'label' => $translator->translate('label.description'),
            'description' => $translator->translate('label.description.node.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('image', 'image', array(
            'label' => $translator->translate('label.image'),
            'description' => $translator->translate('label.image.node.description'),
        ));

        if ($hasThemePermission) {
            $form->addRow('theme', 'select', array(
                'label' => $translator->translate('label.theme'),
                'description' => $translator->translate('label.theme.description'),
                'options' => $this->getThemeOptions($node, $translator, $themes),
            ));
        }

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
                $node->setDescription($locale, $data['description'] ? $data['description'] : null);
                $node->setImage($locale, $data['image'] ? $data['image'] : null);
                $node->setRoute($locale, $data['route'] ? $data['route'] : null);

                if (!$site->isLocalizationMethodCopy()) {
                    $node->setAvailableLocales($locale);
                }

                if ($hasThemePermission) {
                    $node->setTheme($this->getOptionValueFromForm($data['theme']));
                }

                $cms->saveNode($node, (!$node->getId() ? 'Created new page ' : 'Updated page ') . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale),
                ));

                $url = $this->getUrl('cms.page.edit', array(
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
        $this->setTemplateView('cms/backend/page.form', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
