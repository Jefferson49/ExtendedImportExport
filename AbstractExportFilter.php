<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 *                    <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):
 * Copyright (C) 2022 Carmen Just
 *                    <https://justcarmen.nl>
 *
 * DownloadGedcomWithURL (webtrees custom module):
 * Copyright (C) 2023 Markus Hemprich
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
 *
 * 
 * DownloadGedcomWithURL
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module to download or store GEDCOM files on URL requests 
 * with the tree name, GEDCOM file name and authorization provided as parameters within the URL.
 * 
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

use ReflectionClass;
use Throwable;

/**
 * Abstract export filter, which contains basic export filter rules for the mandatory HEAD, SUBM, TRLR structures only
 */
class AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER = [];

    /**
     * Get the export filter
     *
     * @return array
     */
    public function getExportFilter(Tree $tree): array {

        return static::EXPORT_FILTER;
    }

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string $pattern       The pattern of the filter rule, e. g. INDI:BIRT:DATE
     * @param string $gedcom        The Gedcom to convert
     * @param array  $records_list  A list with all xrefs and the related records: array <string xref => Record record>
     * 
     * @return string               The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array $records_list): string {

        //As a default, return the un-converted Gedcom
        return $gedcom;
    }
    
    /**
     * Validate the export filter
     *
     * @return string   Validation error; empty, if successful validation
     */
    public function validate(): string {

        $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';
        $class_name = str_replace($name_space, '', get_class($this));

        $refl         = new ReflectionClass($this);
        $refl_parent  = new ReflectionClass(get_parent_class($this));
        $const        = $refl->getConstant('EXPORT_FILTER');
        $const_parent = $refl_parent->getConstant('EXPORT_FILTER');

        //Validate if const EXPORT_FILTER exists and is not empty
        if ($const === $const_parent) {
            
            return I18N::translate('The selected export filter (%s) does not contain a filter definition (%s) or contains an empty export filter.', $class_name, 'const EXPORT_FILTER');
        }

        //Validate if EXPORT_FILTER contains an array
        if (!is_array(static::EXPORT_FILTER)) {
            return I18N::translate('The selected export filter (%s) contains an invalid filter definition (%s).', $class_name, 'const EXPORT_FILTER');
        }
        
        foreach(static::EXPORT_FILTER as $pattern => $regexps) {

            //Validate filter rule
            if (!is_array($regexps)) {
                return I18N::translate('The selected export filter (%s) contains an invalid filter definition for tag pattern %s.', $class_name, $pattern) . ' ' .
                       I18N::translate('Invalid definition') . ': ' . (string) $regexps;
            }

            //Validate tags
            preg_match_all('/!?([A-Z_\*]+)(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*(:[A-Z_\*]+)*/', $pattern, $match, PREG_PATTERN_ORDER);
            if ($match[0][0] !== $pattern) {
                return I18N::translate('The selected export filter (%s) contains an invalid tag definition', $class_name) . ': ' . $pattern;
            }

            //Validate regular expressions in export filter
            foreach($regexps as $search => $replace) {

                try {
                    preg_match('/' . $search . '/', "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected export filter (%s) contains an invalid regular expression', $class_name) . ': ' . $search . '. ' . I18N::translate('Error message'). ': ' . $th->getMessage();
                }

                try {
                    preg_replace('/' . $search . '/', $replace, "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected export filter (%s) contains an invalid regular expression', $class_name) . ': ' . $replace . '. ' . $th->getMessage();
                }
            }

            //Check if black list filter rules have reg exps
            if (strpos($pattern, '!') === 0) {

                foreach($regexps as $search => $replace) {

                    if ($search !== '' OR $replace !== '') {
                        return I18N::translate('The selected export filter (%s) contains a black list filter rule (%s) with a regular expression, which will never be executed, because the black list filter rule will delete the related GEDCOM line.', $class_name, $pattern);
                    } 
                }
            }

            //Check if a rule is dominated by another rule, which is higher priority (i.e. earlier entry in the export filter list)
            $i = 0;
            $size = sizeof(static::EXPORT_FILTER);
            $pattern_list = array_keys(static::EXPORT_FILTER);

            while($i < $size && $pattern !== $pattern_list[$i]) {

                //If tag ends with :* and ist shorter than pattern from list, extend with further :*
                $extended_pattern = $pattern;
                if (substr_count($pattern, ':') < substr_count($pattern_list[$i], ':') && strpos($pattern, '*' , -1)) {

                    while (substr_count($extended_pattern, ':') < substr_count($pattern_list[$i], ':')) $extended_pattern .=':*';
                }

                if (RemoteGedcomExportService::matchTagWithSinglePattern($extended_pattern, $pattern_list[$i])) {

                    return I18N::translate('The filter rule "%s" is dominated by the earlier filter rule "%s" and will never be executed. Please remove the rule or change the order of the filter rules.', $pattern, $pattern_list[$i]);
                }
                $i++;
            }
        }

        return '';
    }       
 
}
