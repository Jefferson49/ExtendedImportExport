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
 * Interface for export filters
 */
interface ExportFilterInterface
{
    /**
     * Get the export filter
     * 
     * @param  Tree $tree
     *
     * @return array
     */
    public function getExportFilter(Tree $tree): array;

    /**
     * Custom conversion of a Gedcom string
     *
     * @param  string $pattern                  The pattern of the filter rule, e. g. INDI:BIRT:DATE
     * @param  string $gedcom                   The Gedcom to convert
     * @param  array  $empty_records_xref_list  A list with all xrefs of empty records
     * 
     * @return string           The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array $empty_records_xref_list): string;

    /**
     * Validate the export filter
     *
     * @return bool   Validation error; empty, if successful validation
     */
    public function validate(): string;

}
