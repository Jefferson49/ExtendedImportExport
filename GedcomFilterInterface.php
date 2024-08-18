<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2024 webtrees development team
 *                    <http://webtrees.net>

 * DownloadGedcomWithURL (webtrees custom module):
 * Copyright (C) 2024 Markus Hemprich
 *                    <http://www.familienforschung-hemprich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Tree;

/**
 * Interface for GEDCOM filters
 */
interface GedcomFilterInterface
{
    /**
     * Get the Gedcom filter rules
     * 
     * @param array<string> $params   Parameters from remote URL requests 
     *                                as well as further parameters, e.g. 'tree' and 'base_url'
     *
     * @return array
     */
    public function getGedcomFilterRules(array $params = []): array;

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string        $pattern         The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string        $gedcom          The Gedcom to convert
     * @param array         $records_list    A list with all xrefs and the related records: array <string xref => Record record>
     * @param array<string> $params          Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return string                        The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string;

    /**
     * Validate the Gedcom filter
     *
     * @return string   Validation error; empty, if successful validation
     */
    public function validate(): string;

    /**
     * Wether the filter uses a references analysis between the records
     *
     * @return bool   true if reference analysis is used
     */
    public function usesReferencesAnalysis(): bool;

    /**
     * Whether custom tags shall be analyzed and SCHMA structures shall be added to GEDCOM 7
     *
     * @return bool   true if SCHMA analysis is used
     */
    public function usesSchemaTagAnalysis(): bool;

    /**
     * Whether Gedcom lines shall be split (i.e. CONC structure) without leading and trailing spaces
     *
     * @return bool   true if SCHMA analysis is used
     */
    public function wrapLinesWithoutLeadingAndTrailingSpaces(): bool;

    /**
     * Include a set of other filters, which shall be executed before the current filter
     *
     * @return array<GedcomFilterInterface>    A set of included Gedcom filters
     */
    public function getIncludedFiltersBefore(): array;

    /**
     * Include a set of other filters, which shall be executed after the current filter
     *
     * @return array<GedcomFilterInterface>    A set of included Gedcom filters
     */
    public function getIncludedFiltersAfter(): array;
}
