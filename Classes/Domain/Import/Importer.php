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
        $this->postProcessContent($import);

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
        $pages = $this->storage->getAllWpPages();

        $mapping = [0 => $import->getRootPid()];

        // Recursively store pages to create at least partly
        // a page tree (this concept exists not in WP, so you
        // will need to rearrange it at the end).
        while (count($pages)) {
            $pages = $this->processPagesWithParent($pages, $mapping);
            $mapping = $this->storage->getPageMappingWpIdToUid();
        }
    }

    private function processPagesWithParent(array $pages, array $mapping): array
    {
        // Import in first step only pages without parent pages
        $wpPagesParentExists = [];
        $wpPagesParentMissing = [];

        foreach ($pages as $page) {
            if (array_key_exists($page['post_parent'], $mapping)) {
                $page['pid'] = $mapping[$page['post_parent']];
                $wpPagesParentExists[] = $page;
                continue;
            }
            $wpPagesParentMissing[] = $page;
        }

        $this->storePages($wpPagesParentExists);

        return $wpPagesParentMissing;
    }

    private function storePages(array $pages): void
    {
        foreach ($pages as $page) {

            $seoData = $this->storage->getWpSeoData($page['ID']);
            $pid = $this->storage->createPage($page, $seoData);

            if (!$page['post_content']) {
                return;
            }

            [$pageContent, $clusterMatrix] = $this->contentExtractor->extract($page['post_content']);

            $this->storage->createContentElements($pageContent, $clusterMatrix, $pid);
        }
    }

    private function postProcessContent(Import $import): void
    {
        // loop through all content entries
        $contentElements = $this->storage->getAllTypo3Contents();
        $baseUrl = $import->getBaseUrl();
        $baseUrl = str_replace(['/', '.'], ['\/', '\.'], $baseUrl);
        foreach ($contentElements as $contentElement) {
            $bodytext = $contentElement['bodytext'];
            if (!$bodytext) {
                continue;
            }

            // extract links with regex, U = ungreedy
            $wpLinkPattern = '/(<a.*href=")(' . $baseUrl . '\/.*)(".*>)(.*)(<\/a>)/U';

            preg_match_all($wpLinkPattern, $bodytext, $linkMatches, PREG_SET_ORDER);

            foreach ($linkMatches as $match) {

                $uid = $this->storage->getUidByUrl($match[2]);

                $newHref = 't3://page?uid=' . $uid;
                $newLink = $match[1] . $newHref . $match[3] . $match[4] . $match[5];
                $bodytext = str_replace($match[0], $newLink, $bodytext);

            }
            $this->storage->storeBodyOfContentelement($contentElement['uid'], $bodytext);
        }
    }
}