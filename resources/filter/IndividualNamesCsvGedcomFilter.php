<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which creates a CSV list (Surname, Given names) of all individuals 
 */
class IndividualNamesCsvGedcomFilter extends AbstractGedcomFilter
{
    protected const GEDCOM_FILTER_RULES = [
        
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Create the first line of the CSV file with column names (Surname, Given names)
        'HEAD'                      => [".*\n" => "\"Surname\",\"Given names\"\n"],

        //Replace certain characters (*,") by a custom conversion, i.e. call method $this->customConvert(...)
        //Generate CSV data from the individuals name
        'INDI:NAME'                 => ["PHP_function" => "customConvert",
                                        "1 NAME (.*[^ ])? ?\/([^\/]*)\/(.*)\n" => "\"$2\",\"$1\"\n"],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Individual names CSV list');
    }   

    /**
     * Custom conversion of a Gedcom string
     *
     * @param string        $pattern         The pattern of the filter rule, e. g. INDI:*:DATE
     * @param string        $gedcom          The Gedcom to convert
     * @param array         $records_list    A list with all xrefs and the related records: array <string xref => Record record>
     *                                       Records offer methods to be checked whether they are empty, referenced, etc.
     * @param array<string> $params          Parameters from remote URL requests as well as further parameters, e.g. 'tree' and 'base_url'
     * 
     * @return string                        The converted Gedcom
     */
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string {

        //Remove all * " , characters from INDI:NAME
        if ($pattern === 'INDI:NAME') {
            $gedcom = str_replace(['*', '"', ','] , ['', '', ''], $gedcom);
        }

        return $gedcom;
    }
}
