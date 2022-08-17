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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 *
 */
class Storage
{
    private ConnectionPool $connectionPool;

    private DataHandler $dataHandler;

    private ResourceFactory $resourceFactory;

    private ResourceStorage $storage;

    public function __construct(
        ConnectionPool $connectionPool,
        DataHandler $dataHandler,
        ResourceFactory $resourceFactory,
    ) {
        $this->connectionPool = $connectionPool;
        $this->dataHandler = $dataHandler;
        $this->resourceFactory = $resourceFactory;
        $this->storage = $this->resourceFactory->getDefaultStorage();
    }

    public function getWpMetaDataOfImage(string $imageName): ?array
    {
        $metaData = [];
        $metaData['uid'] = $this->getWpUid($imageName);

        if (!$metaData['uid']) {
            #image not found, no further action
            # TODO: Better handling of this situation needed?
            return null;
        }

        $metaData['alternative'] = $this->getWpAlternativeText($metaData['uid']);

        $titleAndExcerpt = $this->getWpPostTitleAndExcerpt($metaData['uid']);
        $metaData = array_merge($metaData, $titleAndExcerpt);

        $metaData['category'] = $this->getWpCategoryNameOfImage($metaData['uid']);

        return $metaData;
    }

    private function getWpUid(string $imageName): int
    {
        $connectionPool = clone $this->connectionPool;

        $result = $connectionPool
            ->getConnectionForTable('wp_postmeta')
            ->select(
                ['post_id'],
                'wp_postmeta',
                [
                    'meta_key' => '_wp_attached_file',
                    'meta_value' => (string)$imageName,
                ]
            )
            ->fetchAssociative();

        return $result ? (int)$result['post_id'] : 0;
    }

    private function getWpAlternativeText(int $uid): string
    {
        $connectionPool = clone $this->connectionPool;
        $result = $connectionPool
            ->getConnectionForTable('wp_postmeta')
            ->select(
                ['meta_value'],
                'wp_postmeta',
                [
                    'meta_key' => '_wp_attachment_image_alt',
                    'post_id' => $uid
                ],
            )
            ->fetchAssociative();

        return $result ? (string)$result['meta_value'] : '';
    }

    private function getWpPostTitleAndExcerpt(int $uid): array
    {
        $connectionPool = clone $this->connectionPool;

        $queryResult = $connectionPool
            ->getConnectionForTable('wp_posts')
            ->select(
                ['post_title', 'post_excerpt'],
                'wp_posts',
                [
                    'ID' => $uid
                ],
            )
            ->fetchAssociative();

        if (!$queryResult) {
            return [];
        }

        $title = $queryResult['post_title'];
        $excerpt = $queryResult['post_excerpt'];

        return ['title' => $title, 'excerpt' => $excerpt];
    }

    private function getWpCategoryNameOfImage(?int $uid): ?string
    {
        $connectionPoolRelation = clone $this->connectionPool;

        $result = $connectionPoolRelation
            ->getConnectionForTable('wp_term_relationships')
            ->select(
                ['term_taxonomy_id'],
                'wp_term_relationships',
                [
                    'object_id' => $uid
                ],
            )
            ->fetchAssociative();

        $categoryUid = $result ? (int)$result['term_taxonomy_id'] : 0;
        if (!$categoryUid) {
            return null;
        }

        $connectionPoolCategory = clone $this->connectionPool;

        $result = $connectionPoolCategory
            ->getConnectionForTable('wp_terms')
            ->select(
                ['name'],
                'wp_terms',
                [
                    'term_id' => $categoryUid
                ],
            )
            ->fetchAssociative();

        return $result ? (string)$result['name'] : '';
    }

    public function getT3File(string $fileName, Import $import): File
    {
        $basePath = $import->getImportDirectory();
        $identifier = '/' . $basePath . $fileName;

        $file = $this->storage->getFileByIdentifier($identifier);

        if (!$file) {
            throw new \Exception('Could not fetch image, might be not indexed yet.', 1660280353);
        }

        if (!$file instanceof File) {
            throw new \Exception('Returned file is not of type `TYPO3\CMS\Core\Resource\File`.', 1660719774);
        }

        return $file;
    }

    public function addWpUidToFile(int $fileUid, int $wpUid): void
    {
        $connectionPool = clone $this->connectionPool;
        $connectionPool
            ->getConnectionForTable('sys_file')
            ->update(
                'sys_file',
                ['wp_id' => $wpUid],
                ['uid' => $fileUid],
            );
    }

    public function addCategoryToFile(int $fileUid, int $categoryUid): void
    {
        // adapt entry in `sys_file_metadata`, add categories = 1 where `file` = UID of image
        $connectionPoolMetaData = clone $this->connectionPool;

        $connectionPoolMetaData
            ->getConnectionForTable('sys_file_metadata')
            ->update(
                'sys_file_metadata',
                ['categories' => 1],
                ['file' => $fileUid],
            );

        // add entry to `sys_category_record_mm` with
        // tablenames = sys_file_metadata    and fieldname = categories and  uid_local = UID of category and uid_foreign = UID of image
        $connectionPoolRecordMm = clone $this->connectionPool;

        $connectionPoolRecordMm
            ->getConnectionForTable('sys_category_record_mm')
            ->insert(
                'sys_category_record_mm',
                [
                    'tablenames' => 'sys_file_metadata',
                    'fieldname' => 'categories',
                    'uid_local' => $categoryUid,
                    'uid_foreign' => $fileUid,
                ]
            );

    }

    public function getOrCreateCategoryUid(string $categoryTitle, Import $import): int
    {
        $categoryPid = $import->getCategoryStorageUid();
        $categoryUid = $this->getCategoryUid($categoryTitle, $categoryPid);

        if ($categoryUid) {
            return $categoryUid;
        }

        $this->createCategory($categoryTitle, $categoryPid);
        $categoryUid = $this->getCategoryUid($categoryTitle, $categoryPid);

        return $categoryUid;
    }

    private function getCategoryUid(string $categoryTitle, int $categoryPid): int
    {
        $connectionPool = clone $this->connectionPool;

        $result = $connectionPool
            ->getConnectionForTable('sys_category')
            ->select(
                ['uid'],
                'sys_category',
                [
                    'title' => $categoryTitle,
                    'pid' => $categoryPid,
                ],
            )
            ->fetchAssociative();

        return $result ? (int)$result['uid'] : 0;
    }

    private function createCategory(string $categoryTitle, int $categoryPid): void
    {
        $dataHandler = clone $this->dataHandler;

        $data = [];
        $data['sys_category']['NEW01'] = [
            'title' => $categoryTitle,
            'pid' => $categoryPid,
        ];

        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
    }

    public function getFileFolder(mixed $categoryTitle): Folder
    {
        $base = '/user_upload';
        $identifier = $base . '/' . $categoryTitle;
        try {
            return $this->storage->getFolder($identifier);
        } catch (\Exception $e) {
            return $this->storage->createFolder($identifier);
        }
    }

    public function moveFileToFolder(File $file, Folder $folder): void
    {
        $this->storage->moveFile($file, $folder);
    }

    public function addMetaDataToFile(File $file, array $metaData): void
    {
        $connectionPoolMetaData = clone $this->connectionPool;

        $connectionPoolMetaData
            ->getConnectionForTable('sys_file_metadata')
            ->update(
                'sys_file_metadata',
                [
                    'title' => $metaData['title'],
                    'description' => $metaData['description'],
                    'alternative' => $metaData['alternative'],
                ],
                ['file' => $file->getUid()],
            );
    }

    public function getAllPages(): array
    {
        $connectionPool = clone $this->connectionPool;

        return $connectionPool
            ->getConnectionForTable('wp_posts')
            ->select(
                [
                    'ID',
                    'post_name',
                    'post_parent',
                    'post_title',
                    'post_content',
                    'post_status',
                ],
                'wp_posts',
                [
                    'post_type' => 'page'
                ],
            )
            ->fetchAllAssociative();
    }

    public function getSeoData(int $wpId): array
    {
        $connectionPool = clone $this->connectionPool;

        $result = $connectionPool
            ->getConnectionForTable('wp_yoast_indexable')
            ->select(
                [
                    'title',
                    'description',
                    'breadcrump_title',
                ],
                'wp_yoast_indexable',
                [
                    'object_id' => $wpId
                ],
            )
            ->fetchAssociative();

        if (!$result) {
            return [];
        }

        return [
            'seo_title' => $result['title'] ?: '',
            'description' => $result['description'] ?: '',
            'nav_title' => $result['breadcrump_title'] ?: '',
        ];
    }

    public function createPage(array $pageData, array $seoData, int $pid): int
    {

        $dataHandler = clone $this->dataHandler;

        $data = [
            'pages' => [
                'NEW_1' => [
                    'pid' => $pid,
                    'title' => $pageData['post_title'],
                    'slug' => $pageData['post_name'],
                    'wp_id' => $pageData['ID'],
                    'hidden' => $pageData['post_status'] !== 'publish' ? 1 : 0,
                    'seo_title' => $seoData['seo_title'],
                    'nav_title' => $seoData['nav_title'],
                    'description' => $seoData['description'],
                ]
            ]
        ];

        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        return $dataHandler->substNEWwithIDs['NEW_1'];
    }

    public function createContentElements(array $pageContent, array $clusterMatrix, int $pid): void
    {
        $dataHandler = clone $this->dataHandler;
        $dataHandler->reverseOrder = true;

        $data = [];
        $data['tt_content'] = [];

        foreach ($clusterMatrix as $cluster) {
            $fields = [];
            $fields['pid'] = $pid;

            $identifierRaw = '';

            foreach ($cluster as $index) {
                $identifierRaw .= (string)$index;
                $element = $pageContent[$index];

                switch ($element['type']) {
                    case 'headline':
                        $fields['CType'] = 'textmedia';
                        $fields['header'] = $element['content']['text'];
                        $fields['header_layout'] = $element['content']['size'];
                        break;

                    case 'paragraph':
                        $fields['CType'] = 'textmedia';
                        $fields['bodytext'] = $element['content']['text'];
                        break;

                    case 'gallery':
                        $fields['CType'] = 'textmedia';
                        $galleryBeneathText = 8;
                        $fields['imageorient'] = $galleryBeneathText;
                        $fields['imagecols'] = (int)$element['content']['imagecols'];

                        // $fields['assets'] = count(explode(',', $element['content']['assets']));

                        // check whether images exist
                        // write into sys_file_reference
                        break;

                    case 'menu_section':
                        $fields['CType'] = 'menu_section';
                        break;
                }
            }

            $identifier = $this->getIdentifier($identifierRaw);

            $data['tt_content'][$identifier] = $fields;
        }

        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
    }

    private function getIdentifier(string $id): string
    {
        $identifier = 'NEW' . sha1($id);

        // Ensure new ID is max 30, as this is max length of the sys_log column
        return substr($identifier, 0, 30);
    }
}