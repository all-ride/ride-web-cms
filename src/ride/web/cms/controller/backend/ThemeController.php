<?php

namespace ride\web\cms\controller\backend;

use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\template\exception\ThemeNotFoundException;
use ride\library\template\engine\EngineModel;
use ride\library\validation\exception\ValidationException;

use ride\web\base\controller\AbstractController;
use ride\web\cms\theme\GenericTheme;
use ride\web\cms\theme\ThemeModel;

class ThemeController extends AbstractController {

    public function indexAction(ThemeModel $themeModel) {
        $this->setTemplateView('cms/backend/theme', array(
            'themes' => $themeModel->getThemes(),
        ));
    }

    public function formAction(EngineModel $engineModel, ThemeModel $themeModel, $theme = null) {
        $translator = $this->getTranslator();

        $themes = $themeModel->getThemes();
        foreach ($themes as $index => $t) {
            $themes[$index] = $t->getDisplayName();
        }
        $themes = array('' => '---') + $themes;

        $engines = $engineModel->getEngines();
        foreach ($engines as $index => $e) {
            $engines[$index] = $e->getName();
        }

        if ($theme) {
            try {
                $theme = $themeModel->getTheme($theme);
            } catch (ThemeNotFoundException $exception) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }

            $data = array(
                'id' => $theme->getName(),
                'name' => $theme->getDisplayName(),
                'parent' => $theme->getParent(),
                'engines' => $theme->getEngines(),
                'regions' => $theme->getRegions(),
            );

            if (isset($themes[$data['id']])) {
                unset($themes[$data['id']]);
            }
        } else {
            $data = null;
        }

        $form = $this->createFormBuilder($data);
        $form->addRow('id', 'hidden');
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.theme'),
            'description' => $translator->translate('label.theme.name.description'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('parent', 'select', array(
            'label' => $translator->translate('label.parent'),
            'description' => $translator->translate('label.theme.parent.description'),
            'options' => $themes,
        ));
        $form->addRow('engines', 'select', array(
            'type' => 'string',
            'label' => $translator->translate('label.engines'),
            'description' => $translator->translate('label.theme.engines.description'),
            'multiple' => true,
            'options' => $engines,
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('regions', 'collection', array(
            'type' => 'string',
            'label' => $translator->translate('label.regions'),
            'description' => $translator->translate('label.theme.regions.description'),
            'filters' => array(
                'trim' => array('empty' => true),
            )
        ));
        $form->setRequest($this->request);

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.theme');
        }

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                if (!$data['id']) {
                    $data['id'] = $data['name'];
                }

                if (!$data['parent']) {
                    $data['parent'] = null;
                }

                foreach ($data['regions'] as $index => $region) {
                    unset($data['regions'][$index]);
                    $data['regions'][$region] = $region;
                }

                $theme = new GenericTheme($data['id'], $data['name'], $data['engines'], $data['regions'], $data['parent']);

                $themeModel->setTheme($theme);

                $this->addSuccess('success.theme.saved', array(
                    'theme' => $data['name'],
                ));

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $form->setValidationException($exception);

                $this->addError('error.validation');

                $this->response->setStatusCode(Response::STATUS_CODE_UNPROCESSABLE_ENTITY);
            }
        }

        if ($theme instanceof GenericTheme) {
            $urlDelete = $this->getUrl('cms.theme.delete', array(
            	'theme' => $theme->getName(),
            )) . '?referer=' . urlencode($this->request->getUrl());
        } else {
            $urlDelete = null;
        }

        $this->setTemplateView('cms/backend/theme.form', array(
            'form' => $form->getView(),
            'theme' => $theme,
            'referer' => $referer,
            'urlDelete' =>  $urlDelete,
        ));
    }

    /**
     * Action to delete a theme
     * @param ride\web\cms\theme\ThemeModel $themeModel
     * @param string $theme
     * @return null
     */
    public function deleteAction(ThemeModel $themeModel, $theme) {
        try {
            $theme = $themeModel->getTheme($theme);
        } catch (ThemeNotFoundException $exception) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.theme');
        }

        if ($this->request->isPost() || $this->request->isDelete()) {
            $themeModel->removeTheme($theme);

            $this->addSuccess('success.theme.deleted', array(
                'theme' => $theme->getDisplayName(),
            ));

            $this->response->setRedirect($this->getUrl('cms.theme'));

            return;
        }

        $this->setTemplateView('cms/backend/theme.delete', array(
            'referer' => $referer,
            'theme' => $theme,
        ));
    }

}