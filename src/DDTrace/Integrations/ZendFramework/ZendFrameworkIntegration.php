<?php

namespace DDTrace\Integrations\ZendFramework;

use DDTrace\Integrations\Integration;
use DDTrace\Util\Runtime;

/**
 * Zend framework integration loader.
 */
class ZendFrameworkIntegration extends Integration
{
    const NAME = 'zendframework';

    /**
     * @return string The integration name.
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * Loads the zend framework integration.
     *
     * @return int
     */
    public static function load()
    {
        if (!self::shouldLoad(self::NAME)) {
            return self::NOT_AVAILABLE;
        }

        // Some frameworks, e.g. Yii registers autoloaders that fails with non-psr4 classes. For this reason the
        // Zend framework integration is not compatible with some of them
        if (Runtime::isAutoloaderRegistered('YiiBase', 'autoload')) {
            return self::NOT_AVAILABLE;
        }

        dd_trace('Zend_Application', 'setOptions', function () {
            $args = func_get_args();
            $options = $args[0];

            $classExist = class_exists('DDTrace_Ddtrace');
            if (!$classExist && !isset($options['resources']['ddtrace'])) {
                $options['autoloaderNamespaces'][] = 'DDTrace_';
                $options['pluginPaths']['DDTrace'] = __DIR__ . '/V1';
                $options['resources']['ddtrace'] = true;
            }

            return dd_trace_forward_call();
        });

        return Integration::NOT_LOADED;
    }
}
