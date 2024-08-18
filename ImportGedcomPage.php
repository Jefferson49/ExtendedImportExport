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

use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Import a GEDCOM file into a tree.
 */
class ImportGedcomPage implements RequestHandlerInterface
{
    use ViewResponseTrait;

    private AdminService $admin_service;

    /**
     * @param AdminService $admin_service
     */
    public function __construct(AdminService $admin_service)
    {
        $this->admin_service = $admin_service;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $tree_name               = Validator::queryParams($request)->string('tree_name');
        $default_gedcom_filter1  = Validator::queryParams($request)->string('default_gedcom_filter1', MoreI18N::xlate('None'));
        $default_gedcom_filter2  = Validator::queryParams($request)->string('default_gedcom_filter2', MoreI18N::xlate('None'));
        $default_gedcom_filter2  = Validator::queryParams($request)->string('default_gedcom_filter3', MoreI18N::xlate('None'));

        $tree_service = new TreeService(new GedcomImportService()); 
        $tree = $tree_service->all()[$tree_name];

        $module_service = new ModuleService();
        $download_gedcom_with_url = $module_service->findByName(DownloadGedcomWithURL::activeModuleName());

        $folder          = $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_FOLDER_TO_SAVE, '');
        $data_filesystem = Registry::filesystem()->root($folder);
        $gedcom_files    = $this->admin_service->gedcomFiles($data_filesystem);

        //Load Gedcom filters
        try {
            DownloadGedcomWithURL::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }

        $gedcom_filter_list = $download_gedcom_with_url->getGedcomFilterList();
        $tree_list = $download_gedcom_with_url->getTreeNameTitleList($tree_service->all());

        return $this->viewResponse(
            DownloadGedcomWithURL::viewsNamespace() . '::import',
            [
                'title'                    => I18N::translate('Extended GEDCOM Import') . ' — ' . e($tree->title()),
                'tree'                     => $tree,
                'tree_list'                => $tree_list,                
                'folder'                   => $folder,
                'gedcom_files'             => $gedcom_files,
                'gedcom_filter_list'       => $gedcom_filter_list,
                'default_gedcom_filter1'   => $default_gedcom_filter1,
                'default_gedcom_filter2'   => $default_gedcom_filter2,
                'default_gedcom_filter3'   => $default_gedcom_filter2,
            ]
        );
    }
}
