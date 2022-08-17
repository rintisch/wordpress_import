<?php

defined('TYPO3_MODE') || die();

$temporaryColumns = [
    'wp_id' => [
        'exclude' => true,
        'label' => 'WordPress ID',
        'config' => [
            'type' => 'input',
            'eval' => 'int',
            'readonly' => true,
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $temporaryColumns);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'wp_id',
    '',
    'after:backend_layout_next_level'
);
