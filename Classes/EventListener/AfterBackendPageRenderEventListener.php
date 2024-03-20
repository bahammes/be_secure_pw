<?php

declare(strict_types=1);


namespace SpoonerWeb\BeSecurePw\EventListener;

use SpoonerWeb\BeSecurePw\Hook\BackendHook;
use SpoonerWeb\BeSecurePw\Hook\RestrictModulesHook;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adds custom renderers for the backend live search
 */
final class AfterBackendPageRenderEventListener
{
    public function __invoke(): void
    {
        // Replaces hook: ['SC_OPTIONS']['typo3/backend.php']['constructPostProcess']
        /** @var BackendHook $backendHook */
        $backendHook = GeneralUtility::makeInstance(BackendHook::class);
        $backendHook->onAfterBackendPageRenderEvent();
        // Replaces hook: ['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php']['renderPreProcess']
        /** @var RestrictModulesHook $restrictModulesHook */
        $restrictModulesHook = GeneralUtility::makeInstance(RestrictModulesHook::class);
        $restrictModulesHook->onAfterBackendPageRenderEvent();
    }
}
