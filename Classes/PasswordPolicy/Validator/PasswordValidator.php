<?php

declare(strict_types=1);

namespace SpoonerWeb\BeSecurePw\PasswordPolicy\Validator;

use SpoonerWeb\BeSecurePw\Service\PawnedPasswordService;
use SpoonerWeb\BeSecurePw\Utilities\TranslationUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\PasswordPolicy\Validator\AbstractPasswordValidator;
use TYPO3\CMS\Core\PasswordPolicy\Validator\Dto\ContextData;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PasswordValidator extends AbstractPasswordValidator
{
    private const PATTERN_LOWER_CHAR = '/[a-z]/';
    private const PATTERN_CAPITAL_CHAR = '/[A-Z]/';
    private const PATTERN_DIGIT = '/[0-9]/';
    private const PATTERN_SPECIAL_CHAR = '/[^0-9a-z]/i';

    public function validate(string $password, ?ContextData $contextData = null): bool
    {
        $isValid = true;
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $messages = [];
        // check for password length; this is already checked in the core policy, but the minimal password
        // length might be higher in be_secure_pw
        // This is only left in to prevent changed behaviour after upgrade
        if (($passwordLengthValue = $this->getMinLength()) && strlen($password) < $passwordLengthValue) {
            /* password too short */
            $isValid = false;

            $passwordToShortString = TranslationUtility::translate('shortPassword');
            $logger->error(sprintf($passwordToShortString, $passwordLengthValue));
            $messages['passwordLength'] = sprintf($passwordToShortString, $passwordLengthValue);
        }

        $counter = 0;
        $notUsed = [];

        $checks = [
            'lowercaseChar' => static::PATTERN_LOWER_CHAR,
            'capitalChar' => static::PATTERN_CAPITAL_CHAR,
            'digit' => static::PATTERN_DIGIT,
            'specialChar' => static::PATTERN_SPECIAL_CHAR,
        ];

        foreach ($checks as $index => $pattern) {
            if ($this->isCheckEnabled($index)) {
                if (preg_match($pattern, $password) > 0) {
                    $counter++;
                } else {
                    $notUsed[] = TranslationUtility::translate($index);
                }
            }
        }

        $patterns = (int) $this->options['patterns'];
        if ($counter < $patterns) {
            /* password does not fit all conventions */
            $ignoredPatterns = $patterns - $counter;

            $additional = '';

            if (is_array($notUsed) && !empty($notUsed)) {
                if (count($notUsed) > 1) {
                    $notUsedConventions = TranslationUtility::translate('notUsedConventions') ?: '';
                    $additional = sprintf($notUsedConventions, implode(', ', $notUsed));
                } else {
                    $notUsedConvention = TranslationUtility::translate('notUsedConvention') ?: '';
                    $additional = sprintf($notUsedConvention, $notUsed[0] ?? '');
                }
            }

            if ($ignoredPatterns >= 1) {
                $label = $ignoredPatterns > 1 ? 'passwordConventions' : 'passwordConvention';
                $logger->error(
                    sprintf(TranslationUtility::translate($label) . $additional, $ignoredPatterns)
                );
                $isValid = false;
                $messages[$label] = sprintf(TranslationUtility::translate($label) . $additional, $ignoredPatterns);
            }
        }

        if ($this->isCheckEnabled('checkPawnedPasswordApi')) {
            $amountOfTimesFoundInDatabase = PawnedPasswordService::checkPassword($password);

            if ($amountOfTimesFoundInDatabase) {
                $isValid = false;
                $messages['checkPawnedPasswordApi'] = sprintf(TranslationUtility::translate('pawnedPassword.message'), $amountOfTimesFoundInDatabase);
            }
        }

        foreach ($messages as $identifier => $message) {
            $this->addErrorMessage($identifier, $message);
        }

        // if password not valid return empty password
        return $isValid;
    }

    private function getMinLength(): int
    {
        $coreMinLength = (int)($this->options['minimumLength'] ?? 8);
        $extMinLength = (int)($this->options['passwordLength'] ?? 0);
        return (int) max($coreMinLength, $extMinLength);
    }

    private function isCheckEnabled(string $checkIdentifier): bool
    {
        return $this->options[$checkIdentifier] ?? false;
    }

    /**
     * Create the options for this password validator; if the default password policy is used, only the validations
     * that don't overlap with the core validator are executed
     * @param string $bePasswordPolicy
     * @return array
     */
    public static function buildValidatorOptions(string $bePasswordPolicy): array
    {
        /** @noinspection NullPointerExceptionInspection */
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('be_secure_pw');
        $policyOptions = [
            'passwordLength' => (int)($extConf['passwordLength'] ?? 0),
            'lowercaseChar' => (bool) ($extConf['lowercaseChar'] ?? false),
            'capitalChar' => (bool) ($extConf['capitalChar'] ?? false),
            'digit' => (bool) ($extConf['digit'] ?? false),
            'specialChar' => (bool) ($extConf['specialChar'] ?? false),
            'checkPawnedPasswordApi' => (bool) ($extConf['checkPawnedPasswordApi'] ?? false),
            'patterns' => (int) ($extConf['patterns'] ?? 0)
        ];
        // If the default password policy is used, the basic checks can already be executed by the core password validator
        // To prevent duplicate execution, the options of this and the core validator are merged
        if ($bePasswordPolicy === 'default') {
            $defaultValidatorConfig =& $GLOBALS['TYPO3_CONF_VARS']['SYS']['passwordPolicies']['default']['validators'][\TYPO3\CMS\Core\PasswordPolicy\Validator\CorePasswordValidator::class];

            $defaultPolicyOptions = $defaultValidatorConfig['options'] ?? [];
            if ($policyOptions['passwordLength'] > ($defaultPolicyOptions['minimumLength'] ?? 0)) {
                $defaultPolicyOptions['minimumLength'] = $policyOptions['passwordLength'];
                $policyOptions['passwordLength'] = 0;
            }
            $mapBoolRequirements = [
                'lowercaseChar' => 'lowerCaseCharacterRequired',
                'capitalChar' => 'upperCaseCharacterRequired',
                'digit' => 'digitCharacterRequired',
                'specialChar' => 'specialCharacterRequired',
            ];
            foreach ($mapBoolRequirements as $confKey => $optionKey) {
                $checkActive = (bool) ($policyOptions[$confKey] ?? false);
                $defaultPolicyOptions[$optionKey] = $checkActive;
                // Option is already checked in the core validator, but still kept enabled,
                // for the 'patterns' count check
                if ($checkActive && !$policyOptions['patterns']) {
                    // Disable if pattern count can be 0
                    $policyOptions[$confKey] = false;
                }
            }
            $defaultValidatorConfig['options'] = $defaultPolicyOptions;
        }
        // If the default password policy is not used, we keep all validations as configured in the extension
        return $policyOptions;
    }
}