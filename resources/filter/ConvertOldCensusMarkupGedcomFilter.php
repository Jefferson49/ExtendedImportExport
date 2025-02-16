<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;


/**
 * A GEDCOM filter, which converts deprecated webtrees 1.7 census markup to markdown
 * 
 * Background: 
 * If the census assistent is used, census data is imported into shared notes with certain markup structures
 * Since webtrees 2.x, markdown is used. In webtrees 1.7, a different markup was used.
 */
class ConvertOldCensusMarkupGedcomFilter extends AbstractGedcomFilter
{    
    protected const GEDCOM_FILTER_RULES = [
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Convert shared notes with a customConvert function
        'NOTE'                      => ["PHP_function" => "customConvert"],

        //Export all other structures      
        '*'                         => [],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Convert webtrees 1.7 census markup to markdown');
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

        //Do nothing if no old markup is found
        if (!strpos($gedcom, '.start_formatted_area.')) {
            return $gedcom;
        }

        $table_header = preg_replace("/1 CONT \.start_formatted_area\.\n1 CONT (.*)\n/", "$1", $gedcom);
        $table_columns = substr_count($table_header, '|');
        $line = '-';

        while ($table_columns > 0) {
            $line .= '|-';
            $table_columns --;
        }

        //RegExp search => replace pairs
        $replace_pairs = [
            "/1 CONT \.start_formatted_area\.\n1 CONT (.*)\n/" => "1 CONT\n1 CONT $1\n1 CONT -|-|-|-|-\n",
            "/1 CONT \.end_formatted_area\.\n/" => "",
            "/\.b\.([^\|\n]+)/" => "**$1**",
            "/\| /" => "|",
            "/ \|/" => "|",
            "/1 CONT (.*)\n/" => "1 CONT |$1|\n",
            "/[ ]+\*\*/" => "**",
        ];

        // Convert markdown
        foreach ($replace_pairs as $search => $replace) {

            $gedcom = preg_replace($search, $replace, $gedcom);
        }

        return $gedcom;
    }
}
