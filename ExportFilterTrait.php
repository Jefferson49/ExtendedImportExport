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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

use Throwable;

/**
 * Trait ModuleCustomTrait - default implementation of ModuleCustomInterface
 */
trait ExportFilterTrait
{
    /**
     * Get the export filter
     *
     * @return array
     */
    public function getExportFilter(Tree $tree): array {

        return self::EXPORT_FILTER;
    }

    /**
     * Validate the export filter
     *
     * @return string   Validation error; empty, if successful validation
     */
    public function validate(): string {

        $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';
        $class_name = str_replace($name_space, '', get_class($this));

        foreach(self::EXPORT_FILTER as $tag => $regexps) {

            //Validate tags
            preg_match_all('/!?([A-Z_\*]+)(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*/', $tag, $match, PREG_PATTERN_ORDER);
            if ($match[0][0] !== $tag) {
                return I18N::translate('The selected export filter (%s) contains an invalid tag definition', $class_name) . ': ' . $tag;
            }

            //Validate regular expressions in export filter
            foreach($regexps as $search => $replace) {

                try {
                    preg_match('/' . $search . '/', "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected export filter (%s) contains an invalid regular expression', $class_name) . ': ' . $search . '. '. $th->getMessage();
                }

                try {
                    preg_replace('/' . $search . '/', $replace, "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected export filter (%s) contains an invalid regular expression', $class_name) . ': ' . $replace . '. ' . $th->getMessage();
                }
            }

            //Check if black list filter rules have reg exps
            if (strpos($tag, '!') === 0) {

                foreach($regexps as $search => $replace) {

                    if ($search !== '' OR $replace !== '') {
                        return I18N::translate('The selected export filter (%s) contains a black list filter rule (%s) with a regular expression, which will never be executed, because the black list filter rule will delete the related GEDCOM line.', $class_name, $tag);
                    } 
                }
            }

            //Check if a rule is dominated by another rule, which is higher priority (i.e. earlier entry in the export filter list)
            $i = 0;
            $size = sizeof(self::EXPORT_FILTER);
            $tag_list = array_keys(self::EXPORT_FILTER);

            while($i < $size && $tag !== $tag_list[$i]) {

                if (RemoteGedcomExportService::matchTagWithSinglePattern($tag, $tag_list[$i])) {

                    return I18N::translate('The filter rule "%s" is dominated by the earlier filter rule "%s" and will never be executed. Please remove the rule or change the order of the filter rules.', $tag, $tag_list[$i]);
                }
                $i++;
            }
        }

        return '';
    }       
}
