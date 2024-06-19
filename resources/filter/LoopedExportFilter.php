<?php

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

/**
 * An export filter, which exports all records (i.e. everything)
 */
class LoopedExportFilter extends AbstractExportFilter implements ExportFilterInterface
{
   protected const EXPORT_FILTER = [
      
      //GEDCOM tag to be exported => Regular expression to be applied for the chosen GEDCOM tag
      //                             ["search pattern" => "replace pattern"],
      '*'                         => [],
   ];

  /**
   * Include other filters, which shall be executed before the current filter
   *
   * @return array<ExportFilterInterface>    Set of included export filters
   */
  public function getIncludedFiltersBefore(): array {

   return [
     //new CombinedExportFilter(),
   ];
 } 
}
