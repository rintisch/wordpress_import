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

/**
 *
 */
class ContentExtractor
{
    private \DOMDocument $dom;

    public function __construct()
    {
        $this->dom = new \DOMDocument();
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
    }

    public function extract(string $content): array
    {
        if (!$content) {
            // Page has no content, so return empty array.
            return [];
        }

        // HACK!
        // Needed because if the first child node is a comment
        // it will be ignored by $body->childNodes later.
        $content = '<p>This paragraph will be ignored</p>' . $content;

        $body = $this->tidyContent($content);

        // Page has no content, so return empty array.
        if (!$body) {
            return [[],[]];
        }

        $pageContent = $this->extractPageContent($body);
        $clusterMatrix = $this->clusterPageContent($pageContent);

        return [$pageContent, $clusterMatrix];
    }

    private function tidyContent(string $content): ?\DOMElement
    {
        $content = $this->convertGalleryShortcodeToPseudoHtml($content);
        $content = $this->convertUntaggedTextToParagraph($content);

        if (!$content) {
            return null;
        }
        $convertedContent = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

        try {
            $this->dom->loadHTML($convertedContent, LIBXML_NOERROR);
            $body = $this->dom->getElementsByTagName('html')[0]->getElementsByTagName('body')[0];
        } catch (\Exception $e) {
            $body = null;
        }
        return $body;
    }

    private function convertGalleryShortcodeToPseudoHtml(string $content): ?string
    {
        return preg_replace('/\[gallery(.*)\]/', '<gallery$1></gallery>', $content);
    }

    /**
     * This is only needed for content which is not in the updated
     * format ("WP Blocks").
     */
    private function convertUntaggedTextToParagraph(?string $content): ?string
    {
        return preg_replace('/^(\w.*)$/m', '<p>$1</p>', $content ?: '');
    }

    /**
     * Create an array that looks e.g. as follows:
     * [
     *    0 = [
     *          type = 'headline',
     *          content =
     *              [
     *                  'type' = '3',
     *                  'text' = 'This is the headline'
     *              ]
     *      ],
     *    1 = [
     *          type = 'paragraph',
     *          content =
     *              [
     *                  'text' = 'This is the paragraph text with a <a href="/">link</a>.'
     *              ]
     *      ],
     *    2 = [
     *          type = 'gallery',
     *          content =
     *              [
     *                  'columns' = '2',
     *                  'images' = '889,890,891,892'
     *              ]
     *      ],
     * ]
     */
    private function extractPageContent(\DOMElement $body): array
    {
        $data = [];

        /** @var \DOMNode $domElement */
        foreach ($body->childNodes as $domElement) {

            // Hack because if the first child node is a comment it will be ignored by $body->childNodes
            // Therefore a paragraph node was added before loading the html.
            if ($domElement === $body->firstChild) {
                continue;
            }

            if ($this->isWhitespaceOnly($domElement)) {
                continue;
            }

            $data = $this->convertText($domElement, $data);
            $data = $this->convertHeadline($domElement, $data);
            $data = $this->convertGallery($domElement, $data);
            $data = $this->convertMenuSection($domElement, $data);
        }
        return $data;
    }

    private function isWhitespaceOnly(\DOMNode $node): bool
    {
        $nodeValueWithoutLinebreaks = str_replace(["\r", "\n"], '', $node->nodeValue ?: '');

        return ($node->nodeName === '#text' && $nodeValueWithoutLinebreaks === '');
    }

    /**
     * Cluster the content to create TYPO3 content elements where
     * a headline is not a single CE if a text CE follows but instead
     * a single CE with a headline and a bodytext.
     *
     * Allowed adjacent contents:
     *  headline + paragraph
     *  headline + gallery
     *  paragraph + gallery
     *  paragraph + paragraph
     *
     * Creates a cluster matrix like e.g.
     * [
     *     1 => [
     *         1,
     *         2
     *     ],
     *     2 => [
     *         3,
     *         4,
     *         5
     *     ]
     * ]
     */
    private function clusterPageContent(array $pageContent): array
    {
        $allowedAdjacents = [
            'headline' => [
                'paragraph',
                'gallery',
            ],
            'paragraph' => [
                'gallery',
                'paragraph'
            ]
        ];

        $clusteredData = [];

        for ($i = 0; $i < count($pageContent); ++$i) {
            if ($i === 0) {
                // This is the first element,
                // so it has to be added in every case.
                $clusteredData[] = [$i];
                continue;
            }

            $previousType = $pageContent[$i - 1]['type'];
            $currentType = $pageContent[$i]['type'];


            // Add index of current element to previous element.
            if (
                array_key_exists($previousType, $allowedAdjacents) &&
                in_array($currentType, $allowedAdjacents[$previousType])
            ) {
                // Cluster these items. therefore add the index to
                // the entry of the previous element.
                $clusteredData[count($clusteredData) - 1][] = $i;
                continue;
            }

            $clusteredData[] = [$i];
        }

        return $clusteredData;
    }

    private function convertText(\DOMNode $domElement, array $data): array
    {
        $allowedTags = ['p', 'ul', 'table', 'strong'];

        if (in_array($domElement->nodeName, $allowedTags)) {

            $text = $this->dom->saveHTML($domElement);

            $text = $domElement->nodeName === 'strong' ? '<p>' . $text . '</p>' : $text;
            $data[] = [
                'type' => 'paragraph',
                'content' => [
                    'text' => $text,
                ]
            ];
        }
        return $data;
    }

    private function convertHeadline(\DOMNode $domElement, array $data): array
    {
        $pattern = '/h(\d)/';
        if (preg_match($pattern, $domElement->nodeName ?: '')) {
            preg_match($pattern, $domElement->nodeName ?: '', $output_array);
            $headSize = $output_array[1];

            $hasAnchor = (int)$this->hasAnchor($domElement);

            $data[] = [
                'type' => 'headline',
                'content' => [
                    'size' => $headSize,
                    'text' => $domElement->nodeValue,
                    'anchor' => $hasAnchor,
                ]
            ];
        }

        return $data;
    }

    private function convertGallery(\DOMNode $domElement, array $data): array
    {
        $patternForGalleryComment = '/[^\/]wp:gallery.*/';
        if (
            $domElement->nodeName === '#comment' &&
            preg_match($patternForGalleryComment, $domElement->nodeValue ?: '')
        ) {
            $patternForIdsAndCols = '/.*"ids":\[(.*)\].*"columns":(\d).*/';
            preg_match($patternForIdsAndCols, $domElement->nodeValue ?: '', $output);

            if(!$output) {
                return $data;
            }
            $ids = $output[1];
            $imagecols = $output[2];

            $data[] = [
                'type' => 'gallery',
                'content' => [
                    'assets' => $ids,
                    'imagecols' => $imagecols,
                ]
            ];
        }
        return $data;
    }

    private function convertMenuSection(\DOMNode $domElement, array $data): array
    {
        if (
            $domElement->nodeName === 'div' &&
            preg_match('/.*jumpnavi.*/', $this->dom->saveHTML($domElement) ?: '')
        ) {
            $data[] = [
                'type' => 'menu_section',
                'content' => '',
            ];
        }
        return $data;
    }

    private function hasAnchor(\DOMElement $domElement): bool
    {

        if($domElement->hasAttribute('id')) {
            return $domElement->getAttribute('id') !== NULL;
        }

        return false;
    }
}