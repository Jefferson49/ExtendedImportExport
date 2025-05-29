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
 * A weebtrees(https://webtrees.net) 2.1 custom module for advanced GEDCOM import, 
 * export and filter operations. The module also supports remote downloads/uploads via URL requests.
 * 
 */

declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\ExtendedImportExport;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Helpers\Functions;
use Jefferson49\Webtrees\Internationalization\MoreI18N;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Convert a GEDCOM file
 */
class ConvertGedcomPage implements RequestHandlerInterface
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

        //If current user is no admin, return to the selection page
        if (!Auth::isAdmin()) { 
            FlashMessages::addMessage(I18N::translate('Access denied. The user needs to be an administrator.'), 'danger');
            return redirect(route(SelectionPage::class));
        }        

        $module_service = new ModuleService();
        $tree_service = new TreeService(new GedcomImportService());

        /** @var DownloadGedcomWithURL $download_gedcom_with_url */
        $download_gedcom_with_url = $module_service->findByName(DownloadGedcomWithURL::activeModuleName());
        
        $gedcom_filename    = Validator::queryParams($request)->string('gedcom_filename', '');
        $filename_converted = Validator::queryParams($request)->string('filename_converted', '');
        $format             = Validator::queryParams($request)->string('format', $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom'));
        $encoding           = Validator::queryParams($request)->string('encoding', $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_ENCODING,  UTF8::NAME));
        $endings            = Validator::queryParams($request)->string('endings', $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_ENDING, 'CRLF'));
        $privacy            = Validator::queryParams($request)->string('privacy', $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_PRIVACY_LEVEL, 'visitor'));
        $time_stamp         = Validator::queryParams($request)->string('time_stamp', $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_DEFAULT_TIME_STAMP, DownloadGedcomWithURL::TIME_STAMP_NONE));
        $gedcom_filter1     = Validator::queryParams($request)->string('gedcom_filter1', MoreI18N::xlate('None'));
        $gedcom_filter2     = Validator::queryParams($request)->string('gedcom_filter2', MoreI18N::xlate('None'));
        $gedcom_filter3     = Validator::queryParams($request)->string('gedcom_filter3', MoreI18N::xlate('None'));

        $folder             = $download_gedcom_with_url->getPreference(DownloadGedcomWithURL::PREF_FOLDER_TO_SAVE, '');
        $data_filesystem    = Registry::filesystem()->root($folder);
        $gedcom_files       = $this->admin_service->gedcomFiles($data_filesystem);

        //Load Gedcom filters
        try {
            DownloadGedcomWithURL::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }       

        $gedcom_filter_list = $download_gedcom_with_url->getGedcomFilterList();
        $tree_list = Functions::getTreeNameTitleList($tree_service->all());

        return $this->viewResponse(
            DownloadGedcomWithURL::viewsNamespace() . '::convert',
            [
                'title'              => I18N::translate('GEDCOM Conversion'),
                'tree_list'          => $tree_list,
                'folder'             => $folder,
                'gedcom_filename'    => $gedcom_filename,
                'filename_converted' => $filename_converted,
                'gedcom_files'       => $gedcom_files,
                'zip_available'      => extension_loaded('zip'),
                'format'             => $format,
                'encoding'           => $encoding,
                'endings'            => $endings,
                'privacy'            => $privacy,
                'time_stamp'         => $time_stamp,
                'gedcom_filter_list' => $gedcom_filter_list,
                'gedcom_filter1'     => $gedcom_filter1,
                'gedcom_filter2'     => $gedcom_filter2,
                'gedcom_filter3'     => $gedcom_filter3,
            ]
        );
    }
}
