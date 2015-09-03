<?php

namespace ride\web\cms\controller\backend;

use ride\library\cms\exception\CmsException;
use ride\library\cms\node\Node;
use ride\library\cms\node\SiteNode;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\node\tree\NodeTreeGenerator;
use ride\web\cms\Cms;

/**
 * Controller for site management
 */
class SiteController extends AbstractNodeTypeController {

    /**
     * Action to show an overview of the sites
     * @param \ride\web\cms\Cms $cms Facade of the CMS
     * @param string $locale Code of the locale
     * @return null
     */
    public function indexAction(Cms $cms, $locale = null) {
        if (!$locale) {
            $locale = $this->getContentLocale();
        } else {
            $this->setContentLocale($locale);
        }

        $defaultRevision = $cms->getDefaultRevision();
        $draftRevision = $cms->getDraftRevision();

        $sites = $cms->getSites();
        if ($sites) {
            foreach ($sites as $siteId => $site) {
                $availableLocales = $site->getAvailableLocales();
                if ($availableLocales == Node::LOCALES_ALL || isset($availableLocales[$locale])) {
                    $siteLocale = $locale;
                } else {
                    $siteLocale = reset($availableLocales);
                }

                if ($site->hasRevision($draftRevision)) {
                    $revision = $draftRevision;
                } elseif ($site->hasRevision($defaultRevision)) {
                    $revision = $defaultRevision;
                } else {
                    $revision = $site->getRevision();
                }

                $sites[$siteId] = array(
                    'name' => $site->getName($locale),
                    'url' => $this->getUrl('cms.site.detail.locale', array(
                        'site' => $site->getId(),
                        'revision' => $revision,
                        'locale' => $siteLocale,
                    )),
                    'data' => $site,
                );
            }

            // When only 1 site is available, redirect to the site instead of
            // showing a dropdown with a single option.
            if (count($sites) == 1) {
                $site = array_shift($sites);
                $this->response->setRedirect($site['url']);

                return;
            }
        }

        $this->setTemplateView('cms/backend/site', array(
            'sites' => $sites,
            'locale' => $locale,
        ));
    }

    /**
     * Action to show the detail of a site
     * @param \ride\web\cms\Cms $cms Facade of the CMS
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $locale Code of the locale
     * @return null
     */
    public function detailAction(Cms $cms, $site, $revision = null, $locale = null) {
        if (!$locale) {
            if ($revision === null) {
                $revision = $cms->getDraftRevision();
            }

            $locale = $this->getContentLocale();

            $this->response->setRedirect($this->getUrl('cms.site.detail.locale', array(
                "site" => $site,
                "revision" => $revision,
                "locale" => $locale,
            )));

            return;
        } else {
            $this->setContentLocale($locale);
        }

        if (!$cms->resolveNode($site, $revision)) {
            return;
        }

        $this->setTemplateView('cms/backend/site.detail', array(
            'site' => $site,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

    /**
     * Action to add or edit a site
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @return null
     */
    public function formAction(Cms $cms, $locale, $site = null, $revision = null) {
        if ($site) {
            if (!$cms->resolveNode($site, $revision)) {
                return;
            }

            $cms->setLastAction('edit');
        } else {
            $site = $cms->createNode('site');
        }

        $this->setContentLocale($locale);

        $translator = $this->getTranslator();
        $locales = $cms->getLocales();
        $themes = $cms->getThemes();

        $referer = $this->request->getQueryParameter('referer');

        $data = array(
            'name' => $site->getName($locale),
            'localizationMethod' => $site->getLocalizationMethod(),
            'baseUrl' => $site->getBaseUrl($locale),
            'theme' => $site->getTheme(),
            'availableLocales' => $this->getLocalesValueFromNode($site),
            'autoPublish' => $site->isAutoPublish(),
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
        $form->addRow('autoPublish', 'boolean', array(
            'label' => $translator->translate('label.publish.auto'),
            'description' => $translator->translate('label.publish.auto.description'),
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

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $oldIsAutoPublish = $data['autoPublish'];

                $data = $form->getData();

                $site->setName($locale, $data['name']);
                if (!$site->getId()) {
                    $locales = $cms->getLocales();
                    foreach ($locales as $l) {
                        if ($l == $locale) {
                            continue;
                        }

                        $site->setName($l, $data['name']);
                    }
                }
                $site->setLocalizationMethod($data['localizationMethod']);
                $site->setBaseUrl($locale, $data['baseUrl']);
                $site->setTheme($data['theme']);
                $site->setAvailableLocales($this->getOptionValueFromForm($data['availableLocales']));
                $site->setIsAutoPublish($data['autoPublish']);

                $cms->saveNode($site, (!$site->getId() ? 'Created new site ' : 'Updated site ') . $site->getName());

                if (!$oldIsAutoPublish && $site->isAutoPublish()) {
                    $cms->publishNode($site);
                }

                $this->addSuccess('success.node.saved', array(
                    'node' => $site->getName($locale),
                ));

                $url = $this->getUrl('cms.site.edit', array(
                    'site' => $site->getId(),
                    'revision' => $site->getRevision(),
                    'locale' => $locale,
                ));

                if ($referer) {
                    $url .= '?referer=' . urlencode($url);
                }

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
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
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @return null
     */
    public function deleteAction(Cms $cms, $locale, $site, $revision) {
        if (!$cms->resolveNode($site, $revision)) {
            return;
        }

        $this->setContentLocale($locale);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $form = $this->createFormBuilder();
        $form->addRow('recursive', 'option', array(
            'label' => '',
            'description' => $translator->translate('label.confirm.node.delete.recursive'),
            'disabled' => true,
            'default' => true,
        ));
        $form = $form->build();

        if ($form->isSubmitted()) {
            $data = $form->getData();

            $cms->removeNode($site, true);

            $this->addSuccess('success.node.deleted', array(
                'node' => $site->getName($locale),
            ));

            $this->response->setRedirect($this->getUrl('cms.site', array('locale' => $locale)));

            return;
        }

        $this->setTemplateView('cms/backend/delete.form', array(
            'form' => $form->getView(),
            'referer' => $referer,
            'site' => $site,
            'node' => $site,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

    /**
     * Action to render the site tree
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $site Id of the site
     * @param string $revision Name of the revision to work with
     * @param string $locale Code of the locale
     * @return null
     */
    public function treeAction(Cms $cms, NodeTreeGenerator $nodeTreeGenerator, $site, $revision, $locale) {
        if (!$cms->resolveNode($site, $revision)) {
            return;
        }

        // initialize response header values
        $eTag = md5('site-' . $site->getId() . '-' . $revision . '-' . $locale . '-' . $site->getDateModified());
        $this->response->setETag($eTag);

        if ($this->response->isNotModified($this->request)) {
            // content is not modified, stop processing
            $this->response->setNotModified();

            return;
        }

        $referer = $this->request->getQueryParameter('referer');

        $siteTreeNode = $nodeTreeGenerator->getTree($site, $locale);

        $this->setTemplateView('cms/backend/site.tree', array(
            'site' => $site,
            'siteTreeNode' => $siteTreeNode,
            'referer' => $referer,
            'locale' => $locale,
        ));
    }

    /**
     * Action to order the nodes of a site
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $site Id of the site
     * @param string $revision Name of the revision to work with
     * @param string $locale Code of the locale
     * @return null
     */
    public function orderAction(Cms $cms, $site, $revision, $locale) {
        if (!$cms->resolveNode($site, $revision)) {
            return;
        }

        $siteId = $site->getId();
        $order = array();

        $data = $this->request->getBodyParameter('data');
        parse_str($data, $data);

        foreach ($data['node'] as $nodeId => $parentId) {
            $order[$nodeId] = 0;

            $isRootNode = $parentId === null || $parentId === 'null';
            if ($isRootNode && $nodeId != $siteId) {
                throw new CmsException('Could not order the tree: nodes are not part of the provided site');
            } elseif (!$isRootNode) {
                $order[$parentId]++;
            }
        }

        unset($order[$siteId]);

        $cms->orderNodes($site, $order, $locale);
    }

    /**
     * Action to clone a node
     * @param \ride\web\cms\Cms $cms
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @return null
     */
    public function cloneAction(Cms $cms, $locale, $site, $revision) {
        if (!$cms->resolveNode($site, $revision)) {
            return;
        }

        $this->setContentLocale($locale);

        $referer = $this->request->getQueryParameter('referer');

        if ($this->request->isPost()) {
            try {
                $clone = $cms->cloneNode($site);

                $this->addSuccess('success.node.cloned', array(
                    'node' => $site->getName($locale),
                ));

                $url = $this->getUrl('cms.site.edit', array(
                    'site' => $site->getId(),
                    'revision' => $site->getRevision(),
                    'locale' => $locale,
                ));

                if ($referer) {
                    $url .= '?referer=' . urlencode($referer);
                }

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception);

                throw $exception;
            }
        }

        $this->setTemplateView('cms/backend/confirm.form', array(
            'type' => 'clone',
            'referer' => $referer,
            'site' => $site,
            'node' => $site,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

    /**
     * Action to publish a revision of a site
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $site Id of the site
     * @param string $revision Name of the revision to work with
     * @param string $locale Code of the locale
     * @return null
     */
    public function publishAction(Cms $cms, $site, $revision, $locale) {
        if (!$cms->resolveNode($site, $revision)) {
            return;
        }

        $this->setContentLocale($locale);

        $publishRevision = $cms->getDefaultRevision();

        $cms->publishNode($site, $publishRevision, true);

        $this->addSuccess('success.node.published', array(
            'node' => $site->getName($locale),
            'revision' => $publishRevision,
        ));

        $referer = $this->request->getQueryParameter('referer');
        if (!$referer) {
            $referer = $this->getUrl('cms.site.detail', array(
                'site' => $site->getId(),
                'revision' => $revision,
                'locale' => $locale,
            ));
        }

        $this->response->setRedirect($referer);
    }

    /**
     * Action to manage the trash of a site
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $site Id of the site
     * @param string $revision Name of the revision to work with
     * @param string $locale Code of the locale
     * @return null
     */
    public function trashAction(Cms $cms, $site, $revision, $locale) {
        if (!$cms->resolveNode($site, $revision)) {
            return;
        }

        $this->setContentLocale($locale);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $trashNodeOptions = array();

        $trashNodes = $cms->getTrashNodes($site->getId());
        foreach ($trashNodes as $trashNodeId => $trashNode) {
            $trashNodeOptions[$trashNodeId] = $trashNode->getNode()->getName($locale) . ' (' . date('Y-m-d H:i:s', $trashNode->getDate()) . ')';
        }

        $form = $this->createFormBuilder();
        $form->addRow('nodes', 'option', array(
            'label' => $translator->translate('label.nodes.trash'),
            'description' => $translator->translate('label.nodes.trash.description'),
            'options' => $trashNodeOptions,
            'multiple' => true,
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('destination', 'select', array(
            'label' => $translator->translate('label.destination'),
            'description' => $translator->translate('label.destination.restore.description'),
            'options' => $cms->getNodeList($site, $locale, true),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $restoreNodes = array();
                foreach ($data['nodes'] as $trashNodeId => $trashNodeName) {
                    $restoreNodes[] = $trashNodes[$trashNodeId];
                }

                $cms->restoreTrashNodes($site, $restoreNodes, $data['destination']);

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('cms/backend/site.trash', array(
            'referer' => $referer,
            'site' => $site,
            'node' => $site,
            'form' => $form->getView(),
            'trashNodes' => $trashNodes,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
