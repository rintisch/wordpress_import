<?php

namespace Rintisch\WordpressImport\Command;

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

use Rintisch\WordpressImport\Domain\Import\Import as ImportModel;
use Rintisch\WordpressImport\Domain\Import\Importer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Command
{
    private Importer $importer;

    public function __construct(
        Importer $importer
    ) {
        parent::__construct();

        $this->importer = $importer;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                'Import data from Wordpress database tables'
            )
            ->addArgument(
                'wpFilesDirectory',
                InputArgument::REQUIRED,
                'Defines path to directory where WP files are stored.'
            )
            ->addArgument('categoryDirectoryUid',
                InputArgument::REQUIRED,
                'UID of directory where categories shall be stored / are stored.'
            )
            ->addArgument('rootPid',
                InputArgument::REQUIRED,
                'UID of page which shall be root for import.'
            )
            ->addArgument('baseUrl',
                InputArgument::REQUIRED,
                'Domain of the page as it is used in WordPress, e.g. https://www.domain.com'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $import = new ImportModel(
            (string)$input->getArgument('wpFilesDirectory'),
            (int)$input->getArgument('categoryDirectoryUid'),
            (int)$input->getArgument('rootPid'),
            (string)$input->getArgument('baseUrl')
        );

        return $this->importer->import($import);
    }
}
