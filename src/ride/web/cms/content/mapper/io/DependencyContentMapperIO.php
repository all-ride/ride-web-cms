<?php

namespace ride\web\cms\content\mapper\io;

use ride\library\cms\content\mapper\io\ContentMapperIO;
use ride\library\dependency\DependencyInjector;

use \Exception;

/**
 * Implementation to load content mappers from the dependency injector
 */
class DependencyContentMapperIO implements ContentMapperIO {

    protected $dependencyInjector;

    /**
     * Constructs a new dependency content mapper io
     * @param \ride\library\dependency\DependencyInjector $dependencyInjector
     * @return null
     */
    public function __construct(DependencyInjector $dependencyInjector) {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * Gets a content mapper
     * @return \ride\library\cms\content\mapper\ContentMapper|null
     */
    public function getContentMapper($type) {
        try {
            return $this->dependencyInjector->get('ride\\library\\cms\\content\\mapper\\ContentMapper', $type);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * Gets the available mappers
     * @return array Array with ContentMapper objects
     * @see ride\library\cms\content\mapper\ContentMapper
     */
    public function getContentMappers() {
        return $this->dependencyInjector->getAll('ride\\library\\cms\\content\\mapper\\ContentMapper');
    }

}