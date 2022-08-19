<?php

namespace Rintisch\WordpressImport\Tests\Functional;

/*
 * Copyright (C) 2022 Gerald Rintisch <gerald.rintisch@posteo.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use Rintisch\WordpressImport\Command\Import;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase as TestCase;

/**
 * @testdox The import
 *
 * @covers \Rintisch\WordpressImport\Command\Import
 * @covers \Rintisch\WordpressImport\Domain\Import\Import
 * @covers \Rintisch\WordpressImport\Domain\Import\Importer
 * @covers \Rintisch\WordpressImport\Domain\Import\Storage
 * @covers \Rintisch\WordpressImport\Domain\Import\Files
 */
class ImportTest extends TestCase
{
    protected $coreExtensionsToLoad = [
        'core',
        'seo',
    ];

    protected $testExtensionsToLoad = [
        'typo3conf/ext/wordpress_import/',
    ];

    protected $additionalFoldersToCreate = [
        'fileadmin/user_upload'
    ];

    protected $pathsToProvideInTestInstance = [
        'typo3conf/ext/wordpress_import/Tests/Functional/ImportFixtures/Files/' => '/fileadmin/user_upload/wp-uploads/',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/ImportFixtures/BeUser.csv');
        $this->setUpBackendUser(2);

        $GLOBALS['LANG'] = $this->getContainer()->get(LanguageServiceFactory::class)->create('default');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS'], $GLOBALS['LANG']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function importImages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/ImportFixtures/ImportImage/Input.csv');

        $importer = $this->getContainer()->get(Import::class);
        $commandTester = new CommandTester($importer);
        $commandTester->execute([
            'wpFilesDirectory' => 'user_upload/wp-uploads/',
            'categoryDirectoryUid' => '1',
            'rootPid' => '2'
        ]);

        $this->assertCSVDataSet(__DIR__ . '/ImportFixtures/ImportImage/Output.csv');
    }

    /**
     * @test
     */
    public function importPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/ImportFixtures/ImportPage/Input.csv');

        $importer = $this->getContainer()->get(Import::class);
        $commandTester = new CommandTester($importer);
        $commandTester->execute([
            'wpFilesDirectory' => 'user_upload/wp-uploads/',
            'categoryDirectoryUid' => '1',
            'rootPid' => '2'
        ]);

        $this->assertCSVDataSet(__DIR__ . '/ImportFixtures/ImportPage/Output.csv');
    }

    /**
     * @test
     */
    public function importPageWithSubPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/ImportFixtures/ImportPageWithSubPage/Input.csv');

        $importer = $this->getContainer()->get(Import::class);
        $commandTester = new CommandTester($importer);
        $commandTester->execute([
            'wpFilesDirectory' => 'user_upload/wp-uploads/',
            'categoryDirectoryUid' => '1',
            'rootPid' => '2'
        ]);

        $this->assertCSVDataSet(__DIR__ . '/ImportFixtures/ImportPageWithSubPage/Output.csv');
    }

    /**
     * @test
     */
    public function importPageWithSubSubPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/ImportFixtures/ImportPageWithSubSubPage/Input.csv');

        $importer = $this->getContainer()->get(Import::class);
        $commandTester = new CommandTester($importer);
        $commandTester->execute([
            'wpFilesDirectory' => 'user_upload/wp-uploads/',
            'categoryDirectoryUid' => '1',
            'rootPid' => '2'
        ]);

        $this->assertCSVDataSet(__DIR__ . '/ImportFixtures/ImportPageWithSubSubPage/Output.csv');
    }
}