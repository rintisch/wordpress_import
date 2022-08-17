<?php


namespace Rintisch\WordpressImport\Domain\Import;

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

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 *
 */
class Files
{
    public function getAllFileNames(Import $import): array
    {
        $files = [];

        $pathToCrawl = $this->getPathToFileadminDirectory() . $import->getImportDirectory();
        $directory = new \DirectoryIterator($pathToCrawl);
        foreach ($directory as $fileinfo) {
            if (!$fileinfo->isDot()) {
                $files[] =$fileinfo->getFilename();
            }
        }

        return $files;
    }

    private function getPathToFileadminDirectory(): string
    {
        // TODO: For live is this needed needed O.o
        // $projectPath = Environment::getPublicPath();
        $projectPath = Environment::getProjectPath();
        return $projectPath . '/fileadmin/';
    }
}