<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Tree;

/**
 * An export filter, which exports birth, marriage, and death data only.
 * 
 * All exported dates are shortened to include the year only (i.e. 01 JAN 1900 => 1900).
 * The export also generates links to the related individuals and families in webtrees.
 */
class BirthMarriageDeathExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
    protected const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:SOUR'                 => [],
      'HEAD:SUBM'                 => [],
      'HEAD:GEDC'                 => [],
      'HEAD:GEDC:VERS'            => [],
      'HEAD:GEDC:FORM'            => [],
      'HEAD:CHAR'                 => [],

      //Add a link (as source citation) to the related individual in webtrees
      //Shorten all included dates to years(i.e. 01 JAN 1900 => 1900)
      'INDI'                      => ["0 @([^@]+)@ INDI\n" => "0 @$1@ INDI\n1 SOUR @S1@\n2 PAGE https://mysite.info/tree/%TREE%/individual/$1\n",
                                      "2 DATE (INT )*(ABT |CAL |EST |AFT |BEF |BET )*(?:.*([\d]{4} AND ))*.*([\d]{4})( .*)*\n" => "2 DATE $1$2$3$4$5\n"],

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
      'INDI:FAMC'                 => [],
      '!INDI:FAMC:NOTE'           => [],
      'INDI:FAMC:*'               => [],
      'INDI:FAMS'                 => [],

      //Add a link (as source citation) to the related individual in webtrees
      //Shorten all included dates to years(i.e. 01 JAN 1900 => 1900)
      'FAM'                       => ["0 @([^@]+)@ FAM\n" => "0 @$1@ FAM\n1 SOUR @S1@\n2 PAGE https://mysite.info/tree/%TREE%/family/$1\n",
                                      "2 DATE (INT )*(ABT |CAL |EST |AFT |BEF |BET )*(?:.*([\d]{4} AND ))*.*([\d]{4})( .*)*\n" => "2 DATE $1$2$3$4$5\n"],

      'FAM:HUSB'                  => [],
      'FAM:WIFE'                  => [],
      'FAM:CHIL'                  => [],

      //Add "Y" to marriage for the case that substructures might be empty      
      'FAM:MARR'                  => ["1 MARR\n$" => "1 MARR Y\n"],
      'FAM:MARR:DATE'             => [],
      'FAM:MARR:PLAC'             => [],
      'FAM:MARR:TYPE'             => [],

      'SUBM'                      => [],
      'SUBM:NAME'                 => [],

      //Add a source to the end of the data. The source is used for links in INDI and FAM (links in souce citations) 
      'TRLR'                      => ["0 TRLR\n" => "0 @S1@ SOUR\n1 TITL https://mysite.info/tree/%TREE%/\n0 TRLR\n"],
  ];


  /**
   * Get the export filter and replace tree name in URLs
   * 
   * In this specific case, the export filter rules are modified to replace 
   * %TREE% in the filter rule by the actual tree name in webtrees
   *
   * @param Tree $tree
   *
   * @return array
   */
  public function getExportFilter(Tree $tree = null): array {

    $export_filter = [];

    foreach(self::EXPORT_FILTER as $tag => $regexps) {

      $replaced_regexps = [];

      foreach($regexps as $search => $replace) {

        //Replace %TREE% in the filter rule by the actual tree name in webtrees
        //This is needed to generated an URL to the records in webtrees
        $replace = str_replace('%TREE%' , $tree !== null ? $tree->name() : '', $replace);
        $replaced_regexps[$search] = $replace;
      }

      $export_filter[$tag] = $replaced_regexps;
    }

    return $export_filter;
  }
}
