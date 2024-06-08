<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Fisharebest\Webtrees\Tree;

/**
 * Tag definitions and regular expressions for export filter
 */
class BirthMarriageDeathExportFilter implements ExportFilterInterface
{
    use ExportFilterTrait;
    public const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      'HEAD'                      => [],
      'HEAD:SOUR'                 => [],
      'HEAD:SUBM'                 => [],
      'HEAD:GEDC'                 => [],
      'HEAD:GEDC:VERS'            => [],
      'HEAD:GEDC:FORM'            => [],
      'HEAD:CHAR'                 => [],

      'INDI'                      => ["0 @([^@]+)@ INDI\n" => "0 @$1@ INDI\n1 SOUR @S1@\n2 PAGE https://mysite.info/tree/%TREE%/individual/$1\n",
                                      "2 DATE (INT )*(ABT |CAL |EST |AFT |BEF |BET )*(?:.*([\d]{4} AND ))*.*([\d]{4})( .*)*\n" => "2 DATE $1$2$3$4$5\n"],
      'INDI:NAME'                 => [],
      'INDI:NAME:TYPE'            => [],
      'INDI:SEX'                  => [],
      'INDI:BIRT'                 => [],
      'INDI:BIRT:DATE'            => [],
      'INDI:BIRT:PLAC'            => [],
      'INDI:CHR'                  => [],
      'INDI:CHR:DATE'             => [],
      'INDI:CHR:PLAC'             => [],
      'INDI:BAPM'                 => [],
      'INDI:BAPM:DATE'            => [],
      'INDI:BAPM:PLAC'            => [],
      'INDI:DEAT'                 => [],
      'INDI:DEAT:DATE'            => [],
      'INDI:DEAT:PLAC'            => [],
      'INDI:FAMC'                 => [],
      '!INDI:FAMC:NOTE'           => [],
      'INDI:FAMC:*'               => [],
      'INDI:FAMS'                 => [],

      'FAM'                       => ["0 @([^@]+)@ FAM\n" => "0 @$1@ FAM\n1 SOUR @S1@\n2 PAGE https://mysite.info/tree/%TREE%/family/$1\n",
                                      "2 DATE (INT )*(ABT |CAL |EST |AFT |BEF |BET )*(?:.*([\d]{4} AND ))*.*([\d]{4})( .*)*\n" => "2 DATE $1$2$3$4$5\n"],
      'FAM:HUSB'                  => [],
      'FAM:WIFE'                  => [],
      'FAM:CHIL'                  => [],
      'FAM:MARR'                  => [],
      'FAM:MARR:DATE'             => [],
      'FAM:MARR:PLAC'             => [],
      'FAM:MARR:TYPE'             => [],

      'SUBM'                      => [],
      'SUBM:NAME'                 => [],

      'TRLR'                      => ["0 TRLR\n" => "0 @S1@ SOUR\n1 TITL https://mysite.info/tree/%TREE%/\n0 TRLR\n"],
  ];


  /**
   * Get the export filter and replace tree name in URLs
   *
   * @return array
   */
  public function getExportFilter(Tree $tree): array {

    $export_filter = [];

    foreach(self::EXPORT_FILTER as $tag => $regexps) {

      $replaced_regexps = [];

      foreach($regexps as $search => $replace) {

        $replace = str_replace('%TREE%' , $tree->name(), $replace);
        $replaced_regexps[$search] = $replace;
      }

      $export_filter[$tag] = $replaced_regexps;
    }

    return $export_filter;
  }
}
