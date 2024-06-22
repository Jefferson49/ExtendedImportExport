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
    //A switch, whether the filter uses a references analysis between the records
    protected const USES_REFERENCES_ANALYSIS = false;

    //A switch, whether custom tags shall be analyzed and SCHMA structures shall be added (only relevant for GEDCOM 7)
    protected const USES_SCHEMA_TAG_ANALYSIS = true;

    //The strings used to identify RegExp macros and PHP functions
    private const REGEXP_MACROS_STRING = 'RegExp_macro';
    private const PHP_FUNCTION_STRING  = 'PHP_function';

    //The definition of the export filter rules. As a default, export all (i.e. '*')
    protected const EXPORT_FILTER_RULES = [

        //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],
        '*'                         => [],
    ];

    //Macros for regular expressions, which can be used in export filter rules
    protected const REGEXP_MACROS = [
    //Name                          => Regular expression to be applied for the chosen GEDCOM tag
    //                                 ["search pattern" => "replace pattern"],
    ];

    /**
     * Get the export filter
     * 
     * @param Tree $tree
     *
     * @return array
     */
    public function getExportFilterRules(Tree $tree = null): array {

        $export_filter_rules = [];

        foreach(static::EXPORT_FILTER_RULES as $tag => $conversion_rule) {

            foreach($conversion_rule as $search => $replace) {

                if ($search === self::REGEXP_MACROS_STRING ) {

                    $regexp  = static::REGEXP_MACROS[$replace];                    
                    $search  = array_key_first($regexp);
                    $replace = $regexp[$search];
                }

                $conversion_rule[$search] = $replace;
            }

            $export_filter_rules[$tag] = $conversion_rule;
        }

        return $export_filter_rules;
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

        //Validate if EXPORT_FILTER contains an array
        if (!is_array(static::EXPORT_FILTER_RULES)) {
            return I18N::translate('The selected export filter (%s) contains an invalid filter definition (%s).', $class_name, 'const EXPORT_FILTER_RULES');
        }

        //Validate if EXPORT_FILTER is empty
        if (sizeof(static::EXPORT_FILTER_RULES) === 0) {
            return I18N::translate('The selected export filter (%s) does not contain any filter rules.', $class_name);
        }

        //Validate if REGEXP_MACROS contains an array
        if (!is_array(static::REGEXP_MACROS)) {
            return I18N::translate('The selected export filter (%s) contains an invalid definition for regular expression macros (%s).', $class_name, 'const REGEXP_MACROS');
        }

        foreach(static::REGEXP_MACROS as $name => $regexps) {

            //Validate regexp macros
            if (!is_array($regexps)) {
                return I18N::translate('The selected export filter (%s) contains an invalid definition for the regular expression macro %s.', $class_name, $name) . ' ' .
                       I18N::translate('Invalid definition') . ': ' . (string) $regexps;
            }
        }

        //Validate, if getExportFilterRules() creates a PHP error
        try {
            $test = $this->getExportFilterRules();
        }
        catch (Throwable $th) {
            return I18N::translate('The %s method of the used export filter (%s) throws a PHP error', 'getExportFilterRules', (new ReflectionClass($this))->getShortName()) .
                                    ': ' . $th->getMessage();
        }

        //Validate if getExportFilterRules returns an array
        if (!is_array($this->getExportFilterRules())) {
            return I18N::translate('The %s method of the export filter (%s) returns an invalid filter definition.', 'getExportFilterRules()', $class_name,);
        }

        foreach($this->getExportFilterRules() as $pattern => $regexps) {

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
                
                //If filter contains 'customConvert' command, check if the filter class has the required method
                if ($search === RemoteGedcomExportService::CUSTOM_CONVERT) {

                    try {
                        $reflection = new ReflectionClass($this);
                        $used_class = $reflection->getMethod('customConvert')->class;
                        if ($used_class !== get_class($this)) {
                            throw new DownloadGedcomWithUrlException();
                        };
                    }
                    catch (Throwable $th) {
                        return I18N::translate('The used export filter (%s) contains a %s command, but the filter class does not contain a %s method.', (new ReflectionClass($this))->getShortName(), RemoteGedcomExportService::CUSTOM_CONVERT, RemoteGedcomExportService::CUSTOM_CONVERT);
                    }
                }

                //Validate regular expressions
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

            //Check if black list filter rules has reg exps
            if (strpos($pattern, '!') === 0) {

                foreach($regexps as $search => $replace) {

                    if ($search !== '' OR $replace !== '') {
                        return I18N::translate('The selected export filter (%s) contains a black list filter rule (%s) with a regular expression, which will never be executed, because the black list filter rule will delete the related GEDCOM line.', $class_name, $pattern);
                    } 
                }
            }

            //Check if a rule is dominated by another rule, which is higher priority (i.e. earlier entry in the export filter list)
            $i = 0;
            $size = sizeof($this->getExportFilterRules());
            $pattern_list = array_keys($this->getExportFilterRules());

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

        //Validate, if getIncludedFiltersBefore creates a PHP error
        try {
            $test = $this->getIncludedFiltersBefore();
        }
        catch (Throwable $th) {
            return I18N::translate('The %s method of the used export filter (%s) throws a PHP error', 'getIncludedFiltersBefore', (new ReflectionClass($this))->getShortName()) .
                                    ': ' . $th->getMessage();
        }                
        //Validate, if getIncludedFiltersAfter creates a PHP error
        try {
            $test = $this->getIncludedFiltersAfter();
        }
        catch (Throwable $th) {
            return I18N::translate('The %s method of the used export filter (%s) throws a PHP error', 'getIncludedFiltersAfter', (new ReflectionClass($this))->getShortName()) .
                                    ': ' . $th->getMessage();
        }
        
        return '';
    }     

    /**
     * Merge two sets of filter rules
     * 
     * @param array $filter_rules1
     * @param array $filter_rules2
     *
     * @return array                 Merged filter rules
     */
    public function mergeFilterRules(array $filter_rules1, array $filter_rules2): array {

        //Add filter rules 1
        $merged_filter_rules = $filter_rules1;

        foreach  ($filter_rules2 as $tag2 => $regexp2) {

            //If tag is not already included, simple add filter rule
            if (!key_exists($tag2, $merged_filter_rules)) {

                $merged_filter_rules[$tag2] = $regexp2;
            }
            //If tag already exists, merge regexps first and afterwards add combined filter rule
            else {
                $regexp1 = $filter_rules1[$tag2];

                //If regexp are different, create merged array of regexps and add to merged filter rules
                if ($regexp1 !== $regexp2) {

                    $merged_filter_rules[$tag2] = array_merge($regexp1, $regexp2);
                }
            }
        }
        return $merged_filter_rules;
    }

    /**
     * Wether the filter uses a references analysis between the records
     *
     * @return bool   true if reference analysis is used
     */
    public function usesReferencesAnalysis(): bool {

        return static::USES_REFERENCES_ANALYSIS;
    }    

    /**
     * Whether custom tags shall be analyzed and SCHMA structures shall be added to GEDCOM 7
     *
     * @return bool   true if SCHMA analysis is used
     */
    public function usesSchemaTagAnalysis(): bool {

        return static::USES_SCHEMA_TAG_ANALYSIS;
    }   

    /**
     * Include a set of other filters, which shall be executed before the current filter
     *
     * @return array<ExportFilterInterface>    A set of included export filters
     */
    public function getIncludedFiltersBefore(): array {

        return [];
    }

    /**
     * Include a set of other filters, which shall be executed after the current filter
     *
     * @return array<ExportFilterInterface>    A set of included export filters
     */
    public function getIncludedFiltersAfter(): array {

        return [];
    }   
}
