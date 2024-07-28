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

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

use ReflectionClass;
use Throwable;

/**
 * Abstract Gedcom filter, which contains basic Gedcom filter rules for the mandatory HEAD, SUBM, TRLR structures only
 */
class AbstractGedcomFilter implements GedcomFilterInterface
{    
    //A switch, whether the filter uses a references analysis between the records
    protected const USES_REFERENCES_ANALYSIS = false;

    //A switch, whether custom tags shall be analyzed and SCHMA structures shall be added (only relevant for GEDCOM 7)
    protected const USES_SCHEMA_TAG_ANALYSIS = true;

    //A switch, whether Gedcom lines shall be split (i.e. CONC structure) without leading and trailing spaces
    protected const WRAP_LINES_WITHOUT_LEADING_AND_TRAILING_SPACES = false;

    //The strings used to identify RegExp macros and PHP functions
    private const REGEXP_MACROS_STRING = 'RegExp_macro';
    public const PHP_FUNCTION_STRING   = 'PHP_function';
    public const CUSTOM_CONVERT        = 'customConvert';

    //The definition of the GEDCOM filter rules. As a default, process all (i.e. '*')
    protected const GEDCOM_FILTER_RULES = [

        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],
        '*'                         => [],
    ];

    //Macros for regular expressions, which can be used in GEDCOM filter rules
    protected const REGEXP_MACROS = [
    //Name                          => Regular expression to be applied for the chosen GEDCOM tag
    //                                 ["search pattern" => "replace pattern"],
    ];

    /**
     * Get the GEDCOM filter
     * 
     * @param Tree $tree
     *
     * @return array
     */
    public function getGedcomFilterRules(Tree $tree = null): array {

        $gedcom_filter_rules = $this->replaceMacros(static::GEDCOM_FILTER_RULES, static::REGEXP_MACROS);
        $gedcom_filter_rules = $this->addPregDelimiters($gedcom_filter_rules);

        return $gedcom_filter_rules;
    }

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string $pattern       The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string $gedcom        The Gedcom to convert
     * @param array  $records_list  A list with all xrefs and the related records: array <string xref => Record record>
     * 
     * @return string               The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list): string {

        //As a default, return the un-converted Gedcom
        return $gedcom;
    }
    
    /**
     * Validate the GEDCOM filter
     *
     * @return string   Validation error; empty, if successful validation
     */
    public function validate(): string {

        $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';
        $class_name = str_replace($name_space, '', get_class($this));

        //Validate if EXPORT_FILTER contains an array
        if (!is_array(static::GEDCOM_FILTER_RULES)) {
            return I18N::translate('The selected GEDCOM filter (%s) contains an invalid filter definition (%s).', $class_name, 'const GEDCOM_FILTER_RULES');
        }

        //Validate if EXPORT_FILTER is empty
        if (sizeof(static::GEDCOM_FILTER_RULES) === 0) {
            return I18N::translate('The selected GEDCOM filter (%s) does not contain any filter rules.', $class_name);
        }

        //Validate if REGEXP_MACROS contains an array
        if (!is_array(static::REGEXP_MACROS)) {
            return I18N::translate('The selected GEDCOM filter (%s) contains an invalid definition for regular expression macros (%s).', $class_name, 'const REGEXP_MACROS');
        }

        foreach(static::REGEXP_MACROS as $name => $regexps) {

            //Validate regexp macros
            if (!is_array($regexps)) {
                return I18N::translate('The selected GEDCOM filter (%s) contains an invalid definition for the regular expression macro %s.', $class_name, $name) . ' ' .
                       I18N::translate('Invalid definition') . ': ' . (string) $regexps;
            }
        }

        //Validate if GEDCOM_FILTER_RULES is an array
        if (!is_array(static::GEDCOM_FILTER_RULES)) {
            return I18N::translate('The variable type of the filter rules definition (%s) of the GEDCOM filter (%s) does not have the type "array".', 'const GEDCOM_FILTER_RULES', $class_name,);
        }

        //Validate GEDCOM_FILTER_RULES
        foreach(static::GEDCOM_FILTER_RULES as $pattern => $regexps) {

            //Validate filter rule
            if (!is_array($regexps)) {
                return I18N::translate('The selected GEDCOM filter (%s) contains an invalid filter definition for tag pattern %s.', $class_name, $pattern) . ' ' .
                       I18N::translate('Invalid definition') . ': ' . (string) $regexps;
            }

            //Validate tags
            preg_match_all('/!?(' . Gedcom::REGEX_TAG . '|[\*)])(\:(' . Gedcom::REGEX_TAG . '|[\*)]))*/', $pattern, $match, PREG_PATTERN_ORDER);

            if ($match[0][0] !== $pattern) {
                return I18N::translate('The selected GEDCOM filter (%s) contains an invalid tag definition', $class_name) . ': ' . $pattern;
            }

            //Validate regular expressions in GEDCOM filter
            foreach($regexps as $search => $replace) {
                
                //If the filter contains a PHP function command, check if the filter class has the required method
                if ($search === self::PHP_FUNCTION_STRING) {

                    //Check if customConvert method is chosen
                    if ($replace !== self::CUSTOM_CONVERT) {

                        return I18N::translate('The used GEDCOM filter (%s) contains a %s command with a method (%s), which is not available. Currently, only "%s" is allowed to be used as a method.', (new ReflectionClass($this))->getShortName(), self::PHP_FUNCTION_STRING, $replace, self::CUSTOM_CONVERT);
                    }
                    try {
                        $reflection = new ReflectionClass($this);
                        $used_class = $reflection->getMethod(self::CUSTOM_CONVERT)->class;
                        if ($used_class !== get_class($this)) {
                            throw new DownloadGedcomWithUrlException();
                        };
                    }
                    catch (Throwable $th) {
                        return I18N::translate('The used GEDCOM filter (%s) contains a %s command with a method (%s), but the filter class does not contain a "%s" method.', (new ReflectionClass($this))->getShortName(), self::PHP_FUNCTION_STRING, self::CUSTOM_CONVERT, self::CUSTOM_CONVERT);
                    }
                }
                //If the filter contains a RegExp macro command, check if the macro exists
                elseif ($search === self::REGEXP_MACROS_STRING) {

                    try {
                        $macro = static::REGEXP_MACROS[$replace];
                    }
                    catch (Throwable $th) {
                        return I18N::translate('The used GEDCOM filter (%s) contains a macro command (%s) for a regular expression, but the macro is not defined in the filter.', (new ReflectionClass($this))->getShortName(), $replace);
                    }
                }

                //Validate regular expressions
                try {
                    preg_match('/' . $search . '/', "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected GEDCOM filter (%s) contains an invalid regular expression', $class_name) . ': ' . $search . '. ' . I18N::translate('Error message'). ': ' . $th->getMessage();
                }

                try {
                    preg_replace('/' . $search . '/', $replace, "Lorem ipsum");
                }
                catch (Throwable $th) {
                    return I18N::translate('The selected GEDCOM filter (%s) contains an invalid regular expression', $class_name) . ': ' . $replace . '. ' . $th->getMessage();
                }
            }

            //Check if black list filter rules has reg exps
            if (strpos($pattern, '!') === 0) {

                foreach($regexps as $search => $replace) {

                    if ($search !== '' OR $replace !== '') {
                        return I18N::translate('The selected GEDCOM filter (%s) contains a black list filter rule (%s) with a regular expression, which will never be executed, because the black list filter rule will delete the related GEDCOM line.', $class_name, $pattern);
                    } 
                }
            }

            //Check if a rule is dominated by another rule, which is higher priority (i.e. earlier entry in the GEDCOM filter list)
            $i = 0;
            $size = sizeof(static::GEDCOM_FILTER_RULES);
            $pattern_list = array_keys(static::GEDCOM_FILTER_RULES);

            while($i < $size && $pattern !== $pattern_list[$i]) {

                //If tag ends with :* and ist shorter than pattern from list, extend with further :*
                $extended_pattern = $pattern;
                if (substr_count($pattern, ':') < substr_count($pattern_list[$i], ':') && strpos($pattern, '*' , -1)) {

                    while (substr_count($extended_pattern, ':') < substr_count($pattern_list[$i], ':')) $extended_pattern .=':*';
                }

                if ($extended_pattern !== '*' && GedcomExportFilterService::matchTagWithSinglePattern($extended_pattern, $pattern_list[$i])) {

                    return I18N::translate('The filter rule "%s" is dominated by the earlier filter rule "%s" and will never be executed. Please remove the rule or change the order of the filter rules.', $pattern, $pattern_list[$i]);
                }
                $i++;
            }            
        }

        //Validate, if getGedcomFilterRules() creates a PHP error
        try {
            $test = $this->getGedcomFilterRules();
        }
        catch (Throwable $th) {
            return I18N::translate('The %s method of the used GEDCOM filter (%s) throws a PHP error', 'getGedcomFilterRules', (new ReflectionClass($this))->getShortName()) .
                                    ': ' . $th->getMessage();
        }        

        //Validate, if getIncludedFiltersBefore creates a PHP error
        try {
            $test = $this->getIncludedFiltersBefore();
        }
        catch (Throwable $th) {
            return I18N::translate('The %s method of the used GEDCOM filter (%s) throws a PHP error', 'getIncludedFiltersBefore', (new ReflectionClass($this))->getShortName()) .
                                    ': ' . $th->getMessage();
        }                
        //Validate, if getIncludedFiltersAfter creates a PHP error
        try {
            $test = $this->getIncludedFiltersAfter();
        }
        catch (Throwable $th) {
            return I18N::translate('The %s method of the used GEDCOM filter (%s) throws a PHP error', 'getIncludedFiltersAfter', (new ReflectionClass($this))->getShortName()) .
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
     * Whether Gedcom lines shall be split (i.e. CONC structure) without leading and trailing spaces
     *
     * @return bool   true if SCHMA analysis is used
     */
    public function wrapLinesWithoutLeadingAndTrailingSpaces(): bool {

        return static::WRAP_LINES_WITHOUT_LEADING_AND_TRAILING_SPACES;
    }     

    /**
     * Include a set of other filters, which shall be executed before the current filter
     *
     * @return array<GedcomFilterInterface>    A set of included GEDCOM filters
     */
    public function getIncludedFiltersBefore(): array {

        return [];
    }

    /**
     * Include a set of other filters, which shall be executed after the current filter
     *
     * @return array<GedcomFilterInterface>    A set of included GEDCOM filters
     */
    public function getIncludedFiltersAfter(): array {

        return [];
    }   

    /**
     * Replace macros in filter rules
     *
     * @param array $filter_rules    A list with filter rules 
     * @param array $regexp_macros   A list with macro definitions
     * 
     * @return array
     */
    public function replaceMacros(array $filter_rules, array $regexp_macros): array {

        $gedcom_filter_rules = [];

        foreach($filter_rules as $tag => $conversion_rules) {

            $modfied_conversion_rules = [];

            foreach($conversion_rules as $search => $replace) {

                if ($search === self::REGEXP_MACROS_STRING) {

                    //Add all the conversion rules found in the macro
                    $modfied_conversion_rules = array_merge($modfied_conversion_rules, $regexp_macros[$replace]);        
                }
                else {
                    //Otherwise add the conversion rule found
                    $modfied_conversion_rules[$search] = $replace;
                }
            }

            $gedcom_filter_rules[$tag] = $modfied_conversion_rules;
        }

        return $gedcom_filter_rules;
    }   
    
    
    /**
     * Add delimiters to regular expression
     *
     * @param array $filter_rules  A list with filter rules 
     * 
     * @return array               Filter rules with delimiters added
     */
    public function addPregDelimiters(array $filter_rules): array {

        $gedcom_filter_rules = [];

        foreach($filter_rules as $tag => $conversion_rules) {

            $modfied_conversion_rules = [];

            foreach($conversion_rules as $search => $replace) {

                if ($search !== self::PHP_FUNCTION_STRING && $search !== self::REGEXP_MACROS_STRING) {

                    //Add delimiters to regular expression
                    $search = '/' . $search .'/'; 
                }

                $modfied_conversion_rules[$search] = $replace;
            }

            $gedcom_filter_rules[$tag] = $modfied_conversion_rules;
        }

        return $gedcom_filter_rules;
    }      
}
