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
class MetaConverter
{
    public function convertMetaData(array $wpMetaData): array
    {
        $metaData = $wpMetaData;

        // Alternative text from WordPress becomes description in TYPO3.
        $metaData['description'] = $wpMetaData['alternative'];

        $metaData['title'] = $this->convertTitle($wpMetaData['title']);
        $metaData['alternative'] = $metaData['title'];

        return $metaData;
    }

    private function convertTitle(string $title): string
    {
        // Remove '-',
        $title = str_replace('-', ' ', $title);

        // oe, ue, ae convert to ö,ü, ä
        $title = str_replace(['ae', 'oe', 'ue'], ['ä', 'ö', 'ü'], $title);

        // weiss to weiß, fuss
        $title = str_replace(['weiss', 'fuss'], ['weiß', 'fuß'], $title);

        // uppercase
        $titleArray = explode(' ', $title);

        $uppercasedTitleArray = array_map([$this, 'checkForUppercase'], $titleArray);

        // Use those values in alternative text
        return implode(' ', $uppercasedTitleArray);
    }

    private function checkForUppercase(string $word): string
    {
        // lowercase for certain words
        //   (alt, neu, restauriert, erneuert, kaputt, sanierte, ueberarbeitet, saniert, deckend, unten, oben, gruen, weiss,
        //    rot, gelb, dunkel, hell, aussen, innen, nachgebaut, lackiert, gebeizt, geoelt, schraeg, weisser, schwarzer, zu,
        //    im, in)
        $keepAsLowercase = [
            'alt',
            'außen',
            'deckend',
            'dunkel',
            'erneuert',
            'gebeizt',
            'gelb',
            'geölt',
            'grün',
            'hell',
            'im',
            'in',
            'innen',
            'kaputt',
            'lackiert',
            'nachgebaut',
            'neu',
            'oben',
            'restauriert',
            'rot',
            'saniert',
            'sanierte',
            'schräg',
            'schwarze',
            'schwarzer',
            'unten',
            'weiß',
            'weiße',
            'weißer',
            'zu',
            'überarbeitet',
        ];

        if(in_array($word, $keepAsLowercase)){ return $word;}

        return ucfirst($word);
    }
}