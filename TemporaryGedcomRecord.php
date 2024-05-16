<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2024 webtrees development team
 *                    <http://webtrees.net>
 *
 * DownloadGedcomWithURL (webtrees custom module):
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
 * DownloadGedcomWithURL
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module to download or store GEDCOM files on URL requests 
 * with the tree name, GEDCOM file name and authorization provided as parameters within the URL.
 * 
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\DownloadGedcomWithURL;

use Exception;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;

use function explode;
use function preg_match;
use function preg_replace;
use function trim;

/**
 * A GEDCOM object.
 */
class TemporaryGedcomRecord extends GedcomRecord
{
    /**
     * Temporarily replace a fact with a new gedcom data. However, do not save to database.
     *
     * @param string $fact_id
     * @param string $gedcom
     * @param bool   $update_chan
     *
     * @return void
     * @throws Exception
     */
    public function updateFact(string $fact_id, string $gedcom, bool $update_chan): void
    {
        // MSDOS line endings will break things in horrible ways
        $gedcom = preg_replace('/[\r\n]+/', "\n", $gedcom);
        $gedcom = trim($gedcom);

        if ($gedcom !== '' && !preg_match('/^1 ' . Gedcom::REGEX_TAG . '/', $gedcom)) {
            throw new Exception('Invalid GEDCOM data passed to GedcomRecord::updateFact(' . $gedcom . ')');
        }

        if ($this->pending !== null && $this->pending !== '') {
            $old_gedcom = $this->pending;
        } else {
            $old_gedcom = $this->gedcom;
        }

        // First line of record may contain data - e.g. NOTE records.
        [$new_gedcom] = explode("\n", $old_gedcom, 2);

        // Replacing (or deleting) an existing fact
        foreach ($this->facts([], false, Auth::PRIV_HIDE, true) as $fact) {
            if ($fact->id() === $fact_id) {
                if ($gedcom !== '') {
                    $new_gedcom .= "\n" . $gedcom;
                }
                $fact_id = 'NOT A VALID FACT ID'; // Only replace/delete one copy of a duplicate fact
            } else {
                $new_gedcom .= "\n" . $fact->gedcom();
            }
        }

        // Adding a new fact
        if ($fact_id === '') {
            $new_gedcom .= "\n" . $gedcom;
        }

        $this->gedcom  = $new_gedcom;
        $this->facts = $this->parseFacts();
    }

    /**
     * Temporarily update this record without saving the changes the database
     *
     * @param string $gedcom
     * @param bool   $update_chan
     *
     * @return void
     */
    public function updateRecord(string $gedcom, bool $update_chan): void
    {
        // MSDOS line endings will break things in horrible ways
        $gedcom = preg_replace('/[\r\n]+/', "\n", $gedcom);
        $gedcom = trim($gedcom);

        $this->facts = $this->parseFacts();
    }

    /**
     * Remove all links from this record to $xref
     *
     * @param string $xref
     * @param bool   $update_chan
     *
     * @return void
     */
    public function removeLinks(string $xref, bool $update_chan): void
    {
        //Do not remove links
        return;
    }

    /**
     * Lock the database row, to prevent concurrent edits.
     */
    public function lock(): void
    {
        //Do not lock
        return;
    }

    /**
     * Change records may contain notes and other fields.  Just update the date/time/author.
     *
     * @param string $gedcom
     *
     * @return string
     */
    private function updateChange(string $gedcom): string
    {
        //Do not update change records
        return $gedcom;
    }

    /**
     * Split the record into facts
     *
     * @return array<Fact>
     */
    private function parseFacts(): array
    {
        // Split the record into facts
        if ($this->gedcom !== '') {
            $gedcom_facts = preg_split('/\n(?=1)/', $this->gedcom);
            array_shift($gedcom_facts);
        } else {
            $gedcom_facts = [];
        }
        if ($this->pending !== null && $this->pending !== '') {
            $pending_facts = preg_split('/\n(?=1)/', $this->pending);
            array_shift($pending_facts);
        } else {
            $pending_facts = [];
        }

        $facts = [];

        foreach ($gedcom_facts as $gedcom_fact) {
            $fact = new Fact($gedcom_fact, $this, md5($gedcom_fact));
            if ($this->pending !== null && !in_array($gedcom_fact, $pending_facts, true)) {
                $fact->setPendingDeletion();
            }
            $facts[] = $fact;
        }
        foreach ($pending_facts as $pending_fact) {
            if (!in_array($pending_fact, $gedcom_facts, true)) {
                $fact = new Fact($pending_fact, $this, md5($pending_fact));
                $fact->setPendingAddition();
                $facts[] = $fact;
            }
        }

        return $facts;
    }

    /**
     * Generate a private version of this record
     *
     * @param int $access_level
     *
     * @return string
     */
    public function createPrivateGedcomRecord(int $access_level): string
    {
        return '0 @' . $this->xref . '@ ' . $this->tag();
    }

    /**
     * Get the GEDCOM tag for this record.
     *
     * @return string
     */
    public function tag(): string
    {
        if (str_starts_with($this->gedcom(), '0 HEAD')) {
            return 'HEAD';
        }
        elseif  (str_starts_with($this->gedcom(), '0 TRLR')) {
            return 'TRLR';
        }
    
        return parent::tag();
    }    
}
