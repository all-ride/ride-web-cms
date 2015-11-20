<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\validation\exception\ValidationException;

use ride\web\cms\form\MetaComponent;
use ride\web\cms\Cms;

/**
 * Controller of the meta node action
 */
class MetaNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
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
    public function indexAction(Cms $cms, MetaComponent $metaComponent, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

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
            'attributes' => array(
                'data-recommended-maxlength' => 60
            )
        ));
        $form->addRow('description', 'text', array(
            'label' => $translator->translate('label.description'),
            'filters' => array(
                'trim' => array(),
            ),
            'attributes' => array(
                'data-recommended-maxlength' => 160
            ),
            'validator' => array(
                'size' => array(
                    'maximum' => 90
                )
            )
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
            'attributes' => array(
                'data-recommended-maxlength' => 60
            )
        ));
        $form->addRow('og-description', 'text', array(
            'label' => $translator->translate('label.description'),
            'filters' => array(
                'trim' => array(),
            ),
            'attributes' => array(
                'data-recommended-maxlength' => 225
            )
        ));
        $form->addRow('og-image', 'image', array(
            'label' => $translator->translate('label.image'),
        ));
        $form->addRow('meta', 'collection', array(
            'label' => $translator->translate('label.meta'),
            'type' => 'component',
            'options' => array(
                'component' => $metaComponent,
            ),
        ));

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

                $cms->saveNode($node, 'Set meta tags to ' . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $url = $this->getUrl(self::ROUTE, array(
                    'site' => $site->getId(),
                    'revision' => $node->getRevision(),
                    'locale' => $locale,
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

        $this->setTemplateView('cms/backend/node.meta', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'parentMeta' => $parentMeta,
        ));
    }

}
