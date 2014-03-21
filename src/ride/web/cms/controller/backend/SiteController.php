<?php

namespace ride\web\cms\controller\backend;

use ride\library\cms\node\exception\NodeNotFoundException;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\SiteNode;
use ride\library\cms\theme\ThemeModel;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\validation\exception\ValidationException;

class SiteController extends AbstractNodeTypeController {

    /**
     * Action to show an overview of the sites
     * @param ride\library\cms\node\NodeModel $nodeModel
     * @return null
     */
    public function indexAction(NodeModel $nodeModel) {
        $this->setTemplateView('cms/backend/site', array(
            'sites' => $nodeModel->getNodesByType('site'),
            'locale' => $this->getLocale(),
        ));
    }

    /**
     * Action to show the detail of a site
     * @param ride\library\cms\node\NodeModel $nodeModel
     * @param string $site
     * @param string $locale
     * @return null
     */
    public function detailAction(I18n $i18n, NodeModel $nodeModel, $site, $locale = null) {
        if (!$locale) {
            $this->response->setRedirect($this->getUrl('cms.site.detail.locale', array(
            	"site" => $site,
                "locale" => $this->getLocale(),
            )));

            return;
        }

        if (!$this->resolveNode($nodeModel, $site)) {
            return;
        }

        $this->setTemplateView('cms/backend/site.detail', array(
            'site' => $site,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

    public function formAction(I18n $i18n, $locale, ThemeModel $themeModel, NodeModel $nodeModel, $site = null) {
        $themes = $themeModel->getThemes();
        $locales = $i18n->getLocaleCodeList();
        $translator = $i18n->getTranslator();

        if ($site) {
            if (!$this->resolveNode($nodeModel, $site)) {
                return;
            }

            $this->setLastAction('edit');
        } else {
            $site = $nodeModel->createNode('site');
        }

        $data = array(
            'name' => $site->getName($locale),
            'localizationMethod' => $site->getLocalizationMethod(),
            'baseUrl' => $site->getBaseUrl($locale),
            'theme' => $site->getTheme(),
            'availableLocales' => $this->getLocalesValueFromNode($site),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('name', 'string', array(
            'label' => $translator->translate('label.site'),
            'description' => $translator->translate('label.site.description'),
            'attributes' => array(
                'autofocus' => 'true',
            ),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('baseUrl', 'website', array(
            'label' => $translator->translate('label.url.base'),
            'description' => $translator->translate('label.url.base.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('theme', 'select', array(
            'label' => $translator->translate('label.theme'),
            'description' => $translator->translate('label.theme.description'),
            'options' => $this->getThemeOptions($site, $translator, $themes),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('availableLocales', 'select', array(
            'label' => $translator->translate('label.locales'),
            'description' => $translator->translate('label.locales.available.description'),
            'options' => $this->getLocalesOptions($site, $translator, $locales),
            'multiple' => true,
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->addRow('localizationMethod', 'select', array(
            'label' => $translator->translate('label.method.localization'),
            'description' => $translator->translate('label.method.localization.description'),
            'readonly' => $site->getId(),
            'options' => array(
                SiteNode::LOCALIZATION_METHOD_COPY => $translator->translate('label.copy.translated'),
                SiteNode::LOCALIZATION_METHOD_UNIQUE => $translator->translate('label.tree.unique'),
            ),
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

                $site->setName($locale, $data['name']);
                if (!$site->getId()) {
                    $site->setLocalizationMethod($data['localizationMethod']);
                }
                $site->setBaseUrl($locale, $data['baseUrl']);
                $site->setTheme($data['theme']);
                $site->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));

                $nodeModel->setNode($site, (!$site->getId() ? 'Created new site ' : 'Updated site ') . $site->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $site->getName($locale),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.site.edit', array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                    )
                ));

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site');
        }

        $this->setTemplateView('cms/backend/site.form', array(
            'node' => $site,
            'referer' => $referer,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $locales,
        ));
    }

    /**
     * Action to delete a site
     * @param ride\library\i18n\I18n $i18n
     * @param string $locale
     * @param ride\library\cms\node\NodeModel $nodeModel
     * @param string $site
     * @return null
     */
    public function deleteAction(I18n $i18n, $locale, NodeModel $nodeModel, $site) {
        if (!$this->resolveNode($nodeModel, $site)) {
            return;
        }

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site');
        }

        if ($this->request->isPost() || $this->request->isDelete()) {
            $nodeModel->removeNode($site);

            $this->addSuccess('success.node.deleted', array(
                'node' => $site->getName($locale),
            ));

            $this->response->setRedirect($this->getUrl('cms.site', array('locale' => $locale)));

            return;
        }

        $this->setTemplateView('cms/backend/confirm.form', array(
            'type' => 'delete',
            'referer' => $referer,
            'site' => $site,
            'node' => $site,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

}
