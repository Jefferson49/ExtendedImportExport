<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

/**
 * A GEDCOM filter, which reduces all dates to years only
 * 
 */
class ReduceDatesToYearsGedcomFilter extends AbstractGedcomFilter implements GedcomFilterInterface
{
    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Do not change dates in HEAD
        'HEAD:DATE'                 => [],
        'HEAD:SOUR:DATA:DATE'       => [],

        //Do not change dates in sources and source citations
        'SOUR:DATA:EVEN:DATE'       => [],
        'SOUR:DATA:DATE'            => [],

        //Do not change dates in CHAN (change) records
        '*:CHAN:DATE'               => [],

        //Do not change dates in _LOC records
        '_LOC:NAME:DATE'            => [],

        //Convert all other dates
        '*:DATE'                 	=> ["RegExp_macro" => "DateToYear"],
        '*:*:DATE'                 	=> ["RegExp_macro" => "DateToYear"],
        '*:*:*:DATE'                => ["RegExp_macro" => "DateToYear"],
        '*:*:*:*:DATE'              => ["RegExp_macro" => "DateToYear"],
        
        //Export other structures      
        '*'                         => [],        
    ];

    protected const REGEXP_MACROS = [
        //Macro Name                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        "DateToYear"                => ["([\d]) DATE (INT )*(ABT |CAL |EST |AFT |BEF |BET )*(?:.*([\d]{4} AND ))*.*([\d]{4})( .*)*" => "$1 DATE $2$3$4$5$6"],
    ];   

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Reduce dates to years');
    }     
}
