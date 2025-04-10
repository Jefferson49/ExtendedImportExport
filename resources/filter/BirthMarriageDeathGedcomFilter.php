<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\I18N;

/**
 * A GEDCOM filter, which includes birth, marriage, and death data only.
 * 
 * The generated GEDCOM also contains links to the related individuals and families in webtrees.
 */
class BirthMarriageDeathGedcomFilter extends AbstractGedcomFilter
{
    protected const GEDCOM_FILTER_RULES = [
      
        //GEDCOM tag                => Regular expression to be applied for the chosen GEDCOM tag
        //                             ["search pattern" => "replace pattern"],
        'HEAD'                      => [],
        'HEAD:SOUR'                 => [],
        'HEAD:SUBM'                 => [],
        'HEAD:GEDC'                 => [],
        'HEAD:GEDC:VERS'            => [],
        'HEAD:GEDC:FORM'            => [],
        'HEAD:CHAR'                 => [],

        //Add a link (as source citation) to the related individual in webtrees
        'INDI'                      => ["0 @([^@]+)@ INDI\n" => "0 @$1@ INDI\n1 SOUR @S1@\n2 PAGE %BASE_URL%/tree/%TREE%/individual/$1\n",
                                        "PHP_function" => "customConvert"],

        'INDI:NAME'                 => [],
        'INDI:NAME:TYPE'            => [],
        'INDI:SEX'                  => [],

        //Add "Y" to birth for the case that substructures might be empty
        'INDI:BIRT'                 => ["1 BIRT\n$" => "1 BIRT Y\n"],
        'INDI:BIRT:DATE'            => [],
        'INDI:BIRT:PLAC'            => [],

        //Add "Y" to christening for the case that substructures might be empty
        'INDI:CHR'                  => ["1 CHR\n$" => "1 CHR Y\n"],
        'INDI:CHR:DATE'             => [],
        'INDI:CHR:PLAC'             => [],
        'INDI:BAPM'                 => [],
        'INDI:BAPM:DATE'            => [],
        'INDI:BAPM:PLAC'            => [],

        //Add "Y" to death for the case that substructures might be empty
        'INDI:DEAT'                 => ["1 DEAT\n$" => "1 DEAT Y\n"],
        'INDI:DEAT:DATE'            => [],
        'INDI:DEAT:PLAC'            => [],

        'INDI:BURI'                 => [],
        'INDI:BURI:DATE'            => [],
        'INDI:BURI:PLAC'            => [],

        'INDI:CREM'                 => [],
        'INDI:CREM:DATE'            => [],
        'INDI:CREM:PLAC'            => [],

        'INDI:OCCU'                 => [],
        'INDI:OCCU:DATE'            => [],
        'INDI:OCCU:PLAC'            => [],

        'INDI:RELI'                 => [],

        'INDI:FAMC'                 => [],
        '!INDI:FAMC:NOTE'           => [],
        'INDI:FAMC:*'               => [],
        'INDI:FAMS'                 => [],

        'INDI:_UID'                 => [],

        //Add a link (as source citation) to the related individual in webtrees
        'FAM'                       => ["0 @([^@]+)@ FAM\n" => "0 @$1@ FAM\n1 SOUR @S1@\n2 PAGE %BASE_URL%/tree/%TREE%/family/$1\n"],

        'FAM:HUSB'                  => [],
        'FAM:WIFE'                  => [],
        'FAM:CHIL'                  => [],

        'FAM:_UID'                  => [],

        //Add "Y" to marriage for the case that substructures might be empty      
        'FAM:MARR'                  => ["1 MARR\n$" => "1 MARR Y\n"],
        'FAM:MARR:DATE'             => [],
        'FAM:MARR:PLAC'             => [],
        'FAM:MARR:TYPE'             => [],

        'SUBM'                      => [],
        'SUBM:NAME'                 => [],
        'SUBM:_UID'                 => [],

        //Add a source to the end of the data. The source is used for links in INDI and FAM (links in souce citations) 
        'TRLR'                      => ["0 TRLR\n" => "0 @S1@ SOUR\n1 TITL %BASE_URL%/tree/%TREE%/\n0 TRLR\n"],
    ];

    /**
     * Get the name of the GEDCOM filter
     * 
     * @return string
     */
    public function name(): string {

        return I18N::translate('Birth, marriage, death export');
    }    

    /**
     * Get the Gedcom filter rules
     * 
     * @param array<string> $params   Parameters from remote URL requests 
     *                                as well as further parameters, e.g. 'tree' and 'base_url'
     *
     * @return array
     */
    public function getGedcomFilterRules(array $params = []): array {

        $gedcom_filter = [];

        if (empty($params)) {
            $params = ['tree' => 'tree', 'base_url' => 'https://mysite.info'];
        }

        foreach(parent::getGedcomFilterRules($params) as $tag => $regexps) {

            $replaced_regexps = [];

            foreach($regexps as $search => $replace) {

                //Replace %TREE% in the filter rule by the actual tree name in webtrees
                //This is needed to generated an URL to the records in webtrees
                $replace = str_replace('%BASE_URL%' , $params['base_url'] !== '' ? $params['base_url'] : 'https://MY_SITE', $replace);
                $replace = str_replace('%TREE%' , $params['tree'] !== '' ? $params['tree'] : 'TREE', $replace);
                $replaced_regexps[$search] = $replace;
            }

            $gedcom_filter[$tag] = $replaced_regexps;
        }

        return $gedcom_filter;
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

        $structures_found = [];
        $tags = [
            'BIRT', 
            'BAPM',
            'CHR',
            'DEAT',
            'BURI',
            'CREM', 
        ];

        foreach($tags as $tag) {
            $structures_found[$tag] = preg_match("/\n1 " . $tag . "/", $gedcom);
        }

        //Delete CHR/BAPM if BIRT was found
        if(($structures_found['BIRT'] ?? 0) === 1) {
            $gedcom = preg_replace("/\n1 CHR(?s).*?\n1/", "\n1", $gedcom);
            $gedcom = preg_replace("/\n1 BAPM(?s).*?\n1/", "\n1", $gedcom);
            //At end of Gedcom string
            $gedcom = preg_replace("/\n1 CHR(?s).*?$/", "", $gedcom);
            $gedcom = preg_replace("/\n1 BAPM(?s).*?$/", "", $gedcom);
        }

        //Delete BAPM if CHR was found
        if(($structures_found['CHR'] ?? 0) === 1) {
            $gedcom = preg_replace("/\n1 BAPM(?s).*?\n1/", "\n1", $gedcom);
            $gedcom = preg_replace("/\n1 BAPM(?s).*?$/", "", $gedcom);
        }

        //Delete BURI/CREM if DEAT was found
        if(($structures_found['DEAT'] ?? 0) === 1) {
            $gedcom = preg_replace("/\n1 BURI(?s).*?\n1/", "\n1", $gedcom);
            $gedcom = preg_replace("/\n1 BURI(?s).*?$/", "", $gedcom);
            $gedcom = preg_replace("/\n1 CREM(?s).*?\n1/", "\n1", $gedcom);
            $gedcom = preg_replace("/\n1 CREM(?s).*?$/", "", $gedcom);
        }

        //Delete CREM if BURI was found
        if(($structures_found['BURI'] ?? 0) === 1) {
            $gedcom = preg_replace("/\n1 CREM(?s).*?\n1/", "\n1", $gedcom);
            $gedcom = preg_replace("/\n1 CREM(?s).*?$/", '', $gedcom);
        }

        return $gedcom;
    }    
}
