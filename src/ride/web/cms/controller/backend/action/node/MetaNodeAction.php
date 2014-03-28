<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\NodeModel;
use ride\library\i18n\I18n;
use ride\library\system\file\browser\FileBrowser;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\form\MetaComponent;

/**
 * Controller of the meta node action
 */
class MetaNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'meta';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.meta';

    /**
     * Perform the meta node action
     */
    public function indexAction(I18n $i18n, $locale, FileBrowser $fileBrowser, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $translator = $this->getTranslator();
        $metaComponent = new MetaComponent();

        $data = array(
            'meta' => array(),
        );

        if ($site->getId() != $node->getId()) {
            $parentMeta = $node->getParentNode()->getMeta($locale);
        } else {
            $parentMeta = array();
        }

        $meta = $node->getMeta($locale, null, false);
        foreach ($meta as $property => $content) {
            switch ($property) {
            	case 'title':
        	        $data['title'] = $content;

            	    break;
            	case 'description':
        	        $data['description'] = $content;

            	    break;
            	case 'keywords':
        	        $data['keywords'] = $content;

            	    break;
            	case 'og:title':
        	        $data['og-title'] = $content;

            	    break;
            	case 'og:description':
        	        $data['og-description'] = $content;

            	    break;
            	case 'og:image':
        	        $data['og-image'] = $content;

            	    break;
            	default:
                    $data['meta'][] = $property . '=' . $content;

            	    break;
            }
        }

        $form = $this->createFormBuilder($data);
        $form->addRow('title', 'string', array(
            'label' => $translator->translate('label.title'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('description', 'text', array(
            'label' => $translator->translate('label.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('keywords', 'string', array(
            'label' => $translator->translate('label.keywords'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('og-title', 'string', array(
            'label' => $translator->translate('label.title'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('og-description', 'text', array(
            'label' => $translator->translate('label.description'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('og-image', 'image', array(
            'label' => $translator->translate('label.image'),
            'path' => $fileBrowser->getPublicDirectory()->getChild('files'),
        ));
        $form->addRow('meta', 'collection', array(
            'label' => $translator->translate('label.meta'),
            'type' => 'component',
            'options' => array(
                'component' => $metaComponent,
            ),
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $meta = array();

                if ($data['title']) {
                    $meta['title'] = $data['title'];
                }
                if ($data['description']) {
                    $meta['description'] = $data['description'];
                }
                if ($data['keywords']) {
                    $meta['keywords'] = $data['keywords'];
                }
                if ($data['og-title']) {
                    $meta['og:title'] = $data['og-title'];
                }
                if ($data['og-description']) {
                    $meta['og:description'] = $data['og-description'];
                }
                if ($data['og-image']) {
                    $meta['og:image'] = $data['og-image'];
                }
                foreach ($data['meta'] as $property) {
                    list($property, $content) = explode('=', $property, 2);

                    $meta[$property] = $content;
                }

                $node->setMeta($locale, $meta);

                $nodeModel->setNode($node, 'Set meta tags to ' . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $this->response->setRedirect($this->request->getUrl());

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/node.meta', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
            'parentMeta' => $parentMeta,
        ));
    }

}
