<?php

declare(strict_types=1);

namespace SpoonerWeb\BeSecurePw\Hook;

/**
 * This file is part of the be_secure_pw project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use SpoonerWeb\BeSecurePw\Utilities\PasswordExpirationUtility;
use SpoonerWeb\BeSecurePw\Utilities\TranslationUtility;
use TYPO3\CMS\Backend\Controller\BackendController;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BackendHook
 *
 * @author Thomas Loeffler <loeffler@spooner-web.de>
 */
class BackendHook
{
    /**
     * constructPostProcess
     *
     * @param array $config
     * @param BackendController $backendReference
     * @deprecated Only used for TYPO3 11.5
     */
    public function constructPostProcess(array $config, BackendController $backendReference): void
    {
        $this->onAfterBackendPageRenderEvent();
    }
    /**
     * constructPostProcess
     */
    public function onAfterBackendPageRenderEvent(): void
    {
        if (!PasswordExpirationUtility::isBeUserPasswordExpired()) {
            return;
        }

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage(
            new FlashMessage(
                TranslationUtility::translate('needPasswordChange.message'),
                TranslationUtility::translate('needPasswordChange.title'),
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::INFO,
                true
            )
        );
    }

    /**
     * looks for a password change and sets the field "tx_besecurepw_lastpwchange" with an actual timestamp
     *
     * @param array $incomingFieldArray
     * @param string $table
     * @param int|string $id
     * @param DataHandler $parentObj
     */
    public function processDatamap_postProcessFieldArray($status, string $table, $id, array &$incomingFieldArray, DataHandler $parentObj)
    {
        if ($table === 'be_users' && !empty($incomingFieldArray['password'])) {
            // only do that, if the record was edited from the user himself
            if ((int)$id === (int)$GLOBALS['BE_USER']->user['uid']
                && empty($GLOBALS['BE_USER']->user['ses_backuserid'])) {
                $incomingFieldArray['tx_besecurepw_lastpwchange'] = time() + (int)date('Z');
            }

            // trigger reload of the backend, if it was previously locked down
            if (PasswordExpirationUtility::isBeUserPasswordExpired()) {
                $GLOBALS['TYPO3_REQUEST'] = $this->getRequest()->withAddedHeader('x-besecurepw-refreshpage', '1');
            }
        }
    }

    private function getRequest(): Request
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
