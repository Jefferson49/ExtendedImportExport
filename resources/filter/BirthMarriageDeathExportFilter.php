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
      'HEAD'                     => [],
      'HEAD:SOUR'                => [],
      'HEAD:SUBM'                => [],
      'HEAD:GEDC'                => [],
      'HEAD:GEDC:VERS'           => [],
      'HEAD:GEDC:FORM'           => [],
      'HEAD:CHAR'                => [],

      'INDI'                     => ["0 @([^@].+)@ INDI\n" => "0 @$1@ INDI\n1 SOUR @S1@\n2 PAGE https://mysite.info/tree/%TREE%/individual/$1\n"],
      'INDI:NAME'                => [],
      'INDI:NAME:TYPE'           => [],
      'INDI:SEX'                 => [],
      'INDI:BIRT'                => [],
      'INDI:BIRT:DATE'           => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI:BIRT:PLAC'           => [],
      'INDI:CHR'                 => [],
      'INDI:CHR:DATE'            => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI:CHR:PLAC'            => [],
      'INDI:BAPM'                => [],
      'INDI:BAPM:DATE'           => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI:BAPM:PLAC'           => [],
      'INDI:DEAT'                => [],
      'INDI:DEAT:DATE'           => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'INDI:DEAT:PLAC'           => [],
      'INDI:FAMC'                => [],
      'INDI:FAMC:*'              => [],
      'INDI:FAMS'                => [],
      'INDI:FAMS:*'              => [],

      'FAM'                      => ["0 @([^@].+)@ FAM\n" => "0 @$1@ FAM\n1 SOUR @S1@\n2 PAGE https://mysite.info/tree/%TREE%/family/$1\n"],
      'FAM:HUSB'                 => [],
      'FAM:WIFE'                 => [],
      'FAM:CHIL'                 => [],
      'FAM:MARR'                 => [],
      'FAM:MARR:DATE'            => ["DATE [^\n]*([\d]{4})\n" => "DATE $1\n"],
      'FAM:MARR:PLAC'            => [],
      'FAM:MARR:TYPE'            => [],

      'SUBM'                     => [],
      'SUBM:NAME'                => [],

      'TRLR'                     => ["0 TRLR\n" => "0 @S1@ SOUR\n1 TITL https://mysite.info/tree/%TREE%/\n0 TRLR\n"],
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
