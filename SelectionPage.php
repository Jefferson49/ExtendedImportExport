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

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Helpers\Functions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * View the settings page
 */
class SelectionPage implements RequestHandlerInterface
{
    use ViewResponseTrait;

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        // If GET request
        if ($request->getMethod() === RequestMethodInterface::METHOD_GET) {
            $tree_name = Validator::queryParams($request)->string('tree', '');
        }
        // If POST request
        elseif ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $tree_name = Validator::parsedBody($request)->string('tree', '');
        }
        else {
            throw new DownloadGedcomWithUrlException(I18N::translate('Internal module error: Neither GET nor POST request received.'));
        }

        $module_service = new ModuleService();
        $download_gedcom_with_url = $module_service->findByName(DownloadGedcomWithURL::activeModuleName());
        $tree_list = Functions::getTreeNameTitleList(Functions::getAllTrees());
        $default_tree = $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_TREE_NAME, '');

        if (Functions::isValidTree($tree_name) && array_key_exists($tree_name, $tree_list)) {
            $tree = Functions::getAllTrees()[$tree_name];
        }
        elseif (Functions::isValidTree($default_tree) && array_key_exists($default_tree, $tree_list)) {
            $tree = Functions::getAllTrees()[$default_tree];
        }
        elseif (sizeof($tree_list) > 0) {
            $tree_name = array_key_first($tree_list);
            $tree = Functions::getAllTrees()[$tree_name];
        }
        else {
            return $download_gedcom_with_url->showErrorMessage(I18N::translate('Tree not found') . ': ' . $tree_name);
        }

        //Set the identifyed tree as the new default tree 
        $download_gedcom_with_url->setPreference(DownloadGedcomWithURL::PREF_DEFAULT_TREE_NAME, $tree->name());

        return $this->viewResponse(
            DownloadGedcomWithURL::viewsNamespace() . '::selection',
            [
                'title'      => I18N::translate('Extended GEDCOM Import/Export'),
                'tree'       => $tree,
                'tree_list'  => $tree_list,
                DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER1 => $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER1, ''),
                DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER2 => $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER2, ''),
                DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER3 => $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_GEDCOM_FILTER3, ''),                
            ]
        );
    }
}
