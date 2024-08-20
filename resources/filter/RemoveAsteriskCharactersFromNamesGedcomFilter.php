<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which removes asterisk characters (i.e. '*') from names.
 * Background: In webtrees, an asterisk character indicates the part of a given name to be underlined.
 */
class RemoveAsteriskCharactersFromNamesGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{    
    protected const GEDCOM_FILTER_RULES = [
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Remove * from names (indicates part of given name underlined in webtrees)
        'INDI:NAME'                 => ["PHP_function" => "customConvert"],

        //Export all other structures      
        '*'                         => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Remove asterisk characters from names');
    } 

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
    public function customConvert(string $pattern, string $gedcom, array &$records_list, array $params = []): string {

        //Remove all * characters
        $gedcom = str_replace('*' , '', $gedcom);

        return $gedcom;
    }
}
