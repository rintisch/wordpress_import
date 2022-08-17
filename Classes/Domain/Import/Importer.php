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

class Importer
{
    private Files $files;

    private Storage $storage;

    private MetaConverter $metaConverter;

    private ContentExtractor $contentExtractor;

    public function __construct(
        Files $files,
        Storage $storage,
        MetaConverter $metaConverter,
        ContentExtractor $contentExtractor
    ) {
        $this->files = $files;
        $this->storage = $storage;
        $this->metaConverter = $metaConverter;
        $this->contentExtractor = $contentExtractor;
    }

    public function import(Import $import): int
    {
        $this->importFiles($import);

        $this->importPages($import);

        return 0;
    }

    public function importFiles(Import $import): void
    {
        $fileNames = $this->files->getAllFileNames($import);

        foreach ($fileNames as $fileName) {
            $wpMetaData = $this->storage->getWpMetaDataOfImage($fileName);

            if (!$wpMetaData) {
                continue;
            }

            $metaData = $this->metaConverter->convertMetaData($wpMetaData);

            // get same image by its identifier in `sys_file`
            $file = $this->storage->getT3File($fileName, $import);

            $this->storage->addWpUidToFile($file->getUid(), $metaData['uid']);

            $this->storage->addMetaDataToFile($file, $metaData);

            if ($metaData['category']) {
                $categoryUid = $this->storage->getOrCreateCategoryUid($metaData['category'], $import);

                // add category to the image
                $this->storage->addCategoryToFile($file->getUid(), $categoryUid);

                // create a file folder for given category
                $folder = $this->storage->getFileFolder($metaData['category']);

                // move the image to the category folder
                $this->storage->moveFileToFolder($file, $folder);
            }
        }
    }

    public function importPages(Import $import): void
    {
        $pages = $this->storage->getAllPages();

        foreach ($pages as $page) {

            // import in first step only pages without parent pages
            if ((int)$page['post_parent'] !== 0) {
                continue;
            }

            $seoData = $this->storage->getSeoData($page['ID']);

            $rootPid = $import->getRootPid();
            $pid = $this->storage->createPage($page, $seoData, $rootPid);

            [$pageContent, $clusterMatrix] = $this->contentExtractor->extract($page['post_content']);

            $this->storage->createContentElements($pageContent, $clusterMatrix, $pid);
            // move on :
            // store guids for convertion of links
            // move through notes and act according to their content
        }
    }
}