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
 * Copyright (C) 2025 Markus Hemprich
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

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Functions to be used in the Extended Import/Export module.
 */
class Functions
{
    /**
     * All the trees, even if current user has no permission to access
     * This is a modified version of the all method of TreeService (which only returns trees with permission)
     *
     * @return Collection<array-key,Tree>
     */
    public static function getAllTrees(): Collection
    {
        if (version_compare(Webtrees::VERSION, '2.2.6', '<')) {

            //Code for webtrees versions < 2.2.6 with old database schema

            return Registry::cache()->array()->remember('all-trees', static function (): Collection {
                // All trees
                $query = DB::table('gedcom')
                    ->leftJoin('gedcom_setting', static function (JoinClause $join): void {
                        $join->on('gedcom_setting.gedcom_id', '=', 'gedcom.gedcom_id')
                            ->where('gedcom_setting.setting_name', '=', 'title');
                    })
                    ->where('gedcom.gedcom_id', '>', 0)
                    ->select([
                        'gedcom.gedcom_id AS tree_id',
                        'gedcom.gedcom_name AS tree_name',
                        'gedcom_setting.setting_value AS tree_title',
                    ])
                    ->orderBy('gedcom.sort_order')
                    ->orderBy('gedcom_setting.setting_value');

                return $query
                    ->get()
                    ->mapWithKeys(static function (object $row): array {
                        return [$row->tree_name => Tree::rowMapper()($row)];
                    });
            });
        }
        else {

            //Code for webtrees versions >= 2.2.6 with new database schema

            return Registry::cache()->array()->remember('all-trees', static function (): Collection {
                // All trees
                $query = DB::table('gedcom')
                    ->where('gedcom.gedcom_id', '>', 0)
                    ->when(!Auth::isAdmin(), function (Builder $query): void {
                        $query->leftJoin('user_gedcom_setting', static function (JoinClause $join): void {
                            $join
                                ->on('user_gedcom_setting.gedcom_id', '=', 'gedcom.gedcom_id')
                                ->where('user_id', '=', Auth::id())
                                ->where('setting_name', '=', UserInterface::PREF_TREE_ROLE);
                        });
                    })
                    ->select(['gedcom.*'])
                    ->orderBy('gedcom.sort_order')
                    ->orderBy('gedcom.title');

                // TODO - do we need the array keys, or would a list of trees be sufficient?
                return $query
                    ->get()
                    ->map(Tree::fromDB(...))
                    ->mapWithKeys(static fn (Tree $tree): array => [$tree->name() => $tree]);
            });
        }
    }
}
