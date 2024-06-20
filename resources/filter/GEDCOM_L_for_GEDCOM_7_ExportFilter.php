<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which handles GEDCOM-L for GEDCOM 7.0
 */
class GEDCOM_L_for_GEDCOM_7_ExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER_RULES = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],

      'INDI:*:_GODP'              => ["2 _GODP (.*)" => "2 ASSO @VOID@\n3 PHRASE $1\n3 ROLE GODP"],

      'FAM:*:_WITN'               => ["2 _WITN (.*)" => "2 ASSO @VOID@\n3 PHRASE $1\n3 ROLE WITN"],
      'INDI:*:_WITN'              => ["2 _WITN (.*)" => "2 ASSO @VOID@\n3 PHRASE $1\n3 ROLE WITN"],

      'FAM:MARR:TYPE'             => ["2 TYPE RELIGIOUS" => "2 TYPE RELI"],

      '*'                         => [],
   ];
}
