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
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('be_secure_pw');
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $messages = [];
        // check for password length
        $passwordLength = $extConf['passwordLength'] ?? 0;
        $passwordLengthValue = (int)$passwordLength;
        if ($passwordLengthValue && strlen($password) < $passwordLengthValue) {
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
            $checkActive = $extConf[$index] ?? false;
            if ($checkActive) {
                if (preg_match($pattern, $password) > 0) {
                    $counter++;
                } else {
                    $notUsed[] = TranslationUtility::translate($index);
                }
            }
        }

        $patterns = $extConf['patterns'] ?? 0;
        if ($counter < $patterns) {
            /* password does not fit all conventions */
            $ignoredPatterns = $patterns - $counter;

            $additional = '';
            $isValid = false;

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
                $messages[$label] = sprintf(TranslationUtility::translate($label) . $additional, $ignoredPatterns);
            }
        }

        $checkPawnedPasswordApi = $extConf['checkPawnedPasswordApi'] ?? false;
        if ($checkPawnedPasswordApi) {
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
}