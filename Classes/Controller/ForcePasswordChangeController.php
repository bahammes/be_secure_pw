<?php

declare(strict_types=1);

namespace SpoonerWeb\BeSecurePw\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SpoonerWeb\BeSecurePw\Database\Event\AddForceResetPasswordLinkEvent;
use SpoonerWeb\BeSecurePw\Utilities\TranslationUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This file is part of the TYPO3 CMS extension "be_secure_pw".
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
class ForcePasswordChangeController
{
    public function forceAction(ServerRequestInterface $request): ResponseInterface
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $userUid = (int)$request->getQueryParams()[AddForceResetPasswordLinkEvent::$passwordChangeCommand];
        $data = [
            'be_users' => [
                $userUid => [
                    'tx_besecurepw_lastpwchange' => 0,
                ],
            ],
        ];
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        $messageQueue = GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier();
        $messageQueue->addMessage(
            new FlashMessage(
                sprintf(TranslationUtility::translate('forcedPasswordChange.message'), $userUid),
                TranslationUtility::translate('forcedPasswordChange.title'),
                AbstractMessage::INFO,
                true
            )
        );

        return new RedirectResponse($request->getServerParams()['HTTP_REFERER']);
    }
}
