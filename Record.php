<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2024 webtrees development team
 *                    <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):
 * Copyright (C) 2022 Carmen Just
 *                    <https://justcarmen.nl>
 *
 * ExtendedImportExport (webtrees custom module):
 * Copyright (C) 2024 Markus Hemprich
 *                    <http://www.familienforschung-hemprich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * 
 * ExtendedImportExport
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module for advanced GEDCOM import, export
 * and filter operations. The module also supports remote downloads/uploads via URL requests.
 * 
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

/**
 * A structure to represent a (Gedcom) record with references to other records
 */
class Record
{
    //The XREF of the record
    private string $xref;

    //Type of the record, i.e. INDI, FAM, ...
    private string $type;

    //A list of records, which contains references pointing to the record
    //array<Record>
    private array $other_records_referencing_record;

    //A list of records, which the record is referencing
    //array<Record>
    private array $other_records_referenced_by_record;

    //Whether the record is empty, i.e. does not contain a Gedcom substructure
    private bool $is_empty;

    //Whether the record is a minimal individual, i.e. INDI record with SEX, FAMC, FAMS or less
    private bool $is_minimal_individual;


    /**
     * Constructor
     *
     * @param string $xref   The XREF of the record
     * 
     * @return void
     */    
    public function __construct($xref, $type = '')
    {
        $this->xref = $xref;
        $this->type = $type;
        $this->other_records_referencing_record = [];
        $this->other_records_referenced_by_record = [];
        $this->is_empty = false;
        $this->is_minimal_individual = false;
    }
    
    /**
     * Whether the records is referenced by other records, which point to it
     *
     * @return bool
     */
    public function isReferenced(): bool {

        return sizeof($this->other_records_referencing_record) > 0;
    }

    /**
     * Whether the records is referencing (i.e. points to) other records
     *
     * @return bool
     */
    public function isReferencing(): bool {

        return sizeof($this->other_records_referenced_by_record) > 0;
    }  
    
    /**
     * Get list of records, which point to the record
     *
     * @return array<Record>
     */
    public function getReferencingRecords(): array {

        return $this->other_records_referencing_record;
    }

    /**
     * Get list of records, to which the record points to
     *
     * @return array<Record>
     */
    public function getReferencedRecords(): array {

        return $this->other_records_referenced_by_record;
    }

    /**
     * Set record to empty
     *
     * @return void
     */
    public function setEmpty(): void {

        $this->is_empty = true;
        return;
    }
    
    /**
     * Whether the records is empty, i.e. does not contain a Gedcom substructure
     *
     * @return bool
     */
    public function isEmpty(): bool {

        return $this->is_empty;
    }

    /**
     * Set record as minimal individual
     *
     * @return void
     */
    public function setMinimalIndividual(): void {

        $this->is_minimal_individual = true;
        return;
    }
    
    /**
     * Whether the records is a minimal individual, i.e. INDI record with SEX, FAMC, FAMS or less
     *
     * @return bool
     */
    public function isMinimalIndividual(): bool {

        return $this->is_minimal_individual;
    }    

    /**
     * Add other record, which points to the record
     *
     * @param Record $record
     * 
     * @return void
     */
    public function addReferencingRecord(Record $record): void {

        $this->other_records_referencing_record[] = $record;
        return;
    }

    /**
     * Add other record, which is references by the record
     *
     * @param Record $record
     * 
     * @return void
     */
    public function addReferencedRecord(Record $record): void {

        $this->other_records_referenced_by_record[] = $record;
        return;
    }

    /**
     * Remove reference of other record, which points to the record
     *
     * @param Record $record
     * 
     * @return void
     */
    public function removeReferencingRecord(Record $record): void {

        $key = array_search($record, $this->other_records_referencing_record);
        unset($this->other_records_referencing_record[$key]);
        return;
    }

    /**
     * Remove other record, which is referenced by the record
     *
     * @param Record $record
     * 
     * @return void
     */
    public function removeReferencedRecord(Record $record): void {

        $key = array_search($record, $this->other_records_referenced_by_record);
        unset($this->other_records_referenced_by_record[$key]);
        return;
    }

    /**
     * Set record tpye
     * 
     * @param string $type  A record type, i.e. INDI, FAM, ...
     *
     * @return void
     */
    public function setTpye(string $type): void {

        $this->type = $type;
        return;
    }
    
    /**
     * Get the record tpye
     * 
     * @return string
     */
    public function type(): string {

        return $this->type;
    }

    /**
     * Get the record xref
     * 
     * @return string
     */
    public function xref(): string {

        return $this->xref;
    }    

    /**
     * Whether the record exists (in the overall GEDCOM data)
     * 
     * @return bool
     */
    public function exists(): bool {

        //If the type is set for the record, we assume that it exists
        return $this->type() !== '';
    }    
}
