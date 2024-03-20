<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$tempColumns = [
    'tx_besecurepw_lastpwchange' => [
        'exclude' => 0,
        'label' => 'LLL:EXT:be_secure_pw/Resources/Private/Language/locallang.xml:be_users.tx_besecurepw_lastpwchange',
        'config' => [
            'type' => 'input',
            'size' => 12,
            'eval' => 'datetime',
            'renderType' => 'inputDateTime',
            'default' => 0,
            'readOnly' => true,
        ],
    ],
    'tx_besecurepw_forcepasswordchange' => [
        'exclude' => 0,
        'label' => 'LLL:EXT:be_secure_pw/Resources/Private/Language/locallang.xml:be_users.tx_besecurepw_forcepasswordchange',
        'config' => [
            'type' => 'user',
            'renderType' => 'forcePasswordChangeButton',
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('be_users', $tempColumns);
ExtensionManagementUtility::addToAllTCAtypes(
    'be_users',
    '--div--;Secure Password,tx_besecurepw_lastpwchange,tx_besecurepw_forcepasswordchange'
);
if (empty($GLOBALS['TCA']['be_users']['columns']['password']['config']['passwordPolicy'])) {
    $GLOBALS['TCA']['be_users']['columns']['password']['config']['passwordPolicy'] = 'default';
}