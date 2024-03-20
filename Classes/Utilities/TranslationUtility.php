<?php

declare(strict_types=1);

namespace SpoonerWeb\BeSecurePw\Utilities;


use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class TranslationUtility
 */
class TranslationUtility
{
    protected static $languageService = null;
    protected const EXT_LL_PREFIX = 'LLL:EXT:be_secure_pw/Resources/Private/Language/locallang.xlf:';

    public static function translate(string $key): string
    {
        return self::getLanguageService()->sL(self::EXT_LL_PREFIX . $key);

    }

    protected static function getLanguageService(): LanguageService
    {
        if (null === self::$languageService) {
            $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
            if ($request instanceof ServerRequestInterface && ApplicationType::fromRequest($request)->isFrontend()) {
                $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
                return $languageServiceFactory->createFromSiteLanguage($request->getAttribute('language')
                    ?? $request->getAttribute('site')->getDefaultLanguage());
            }

            if (($GLOBALS['LANG'] ?? null) instanceof LanguageService) {
                return $GLOBALS['LANG'];
            }

            $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
            self::$languageService = $languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER'] ?? null);
        }
        return self::$languageService;
    }
}
