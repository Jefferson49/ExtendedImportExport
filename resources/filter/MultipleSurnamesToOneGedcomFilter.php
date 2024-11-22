<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which converts NAME patterns with multiple to one single surname
 */
class MultipleSurnamesToOneGedcomFilter extends AbstractGedcomFilter
{    
    private const SURNAME = '%_SURNAME_%';
    protected const GEDCOM_FILTER_RULES = [
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],

        //Convert names
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

        return I18N::translate('Convert multiple surnames to a single surname');
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

        //Extract NAME data
        preg_match_all("/1 NAME (.*)/", $gedcom, $matches, PREG_SET_ORDER);
        $name =  $matches[0][1] ?? '';

        //Extract surname pieces
        preg_match_all("/\/(.*?)\//", $name, $matches, PREG_PATTERN_ORDER);
        $surnames_with_slashes = $matches[0] ?? [];
        $surnames = $matches[1] ?? [];

        //If only a single surname is found, do nothing
        if (sizeof($surnames) < 2) return $gedcom;
        
        //Replace identified surnames by placeholders
        foreach ($surnames_with_slashes as $search) {
            $name = str_replace($search, self::SURNAME, $name);
        }

        //Extract surname prefix tokens
        preg_match_all("/2 SPFX (.*)/", $gedcom, $matches, PREG_SET_ORDER);
        $spfx_tokens = preg_split('/,[ ]*/', $matches[0][1] ?? '');

        //Extract name pieces
        $name_pieces = explode(' ', $name);

        //Reassemple first name and surname
        $first_name = '';
        $surname = '';
        $starts_with_first_name = false;

        for ($i = 0; $i < sizeof($name_pieces); $i++) {
            //If surname
            if ($name_pieces[$i] === self::SURNAME) {
                $surname !== '' ? $surname .= ' ' : '';
                $surname .= array_shift($surnames);
            }
            //If surname prefix
            elseif  (in_array($name_pieces[$i], $spfx_tokens) && ($name_pieces[$i+1] ?? '') === self::SURNAME) {
                $surname !== '' ? $surname .= ' ' : '';
                $surname .= $name_pieces[$i];
            }
            else {
                if ($i === 0) $starts_with_first_name = true;
                $first_name !== '' ? $first_name .= ' ' : '';
                $first_name .= $name_pieces[$i];
            }
        }

        //Reassemble name
        if ($starts_with_first_name) {
            $name = $first_name . ' /' . $surname . '/';
        }
        else {
            $name = '/' . $surname . '/ ' . $first_name;
        }

        $gedcom = preg_replace("/1 NAME (.*)/", '1 NAME ' . $name, $gedcom);

        //Replace SURN in Gedcom
        $gedcom = preg_replace("/2 SURN (.*)/", '2 SURN ' . $surname, $gedcom);

        //Delete SPFX in Gedcom
        $gedcom = preg_replace("/2 SPFX (.*)\n/", '', $gedcom);

        return $gedcom;
    }
}
