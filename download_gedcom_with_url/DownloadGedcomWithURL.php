<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
 * Copyright (C) 2022 Webmaster @ Familienforschung Hemprich, 
 *                    <http://www.familienforschung-hemprich.de>
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
 *
 * DownloadGedcomWithURL
 *
 * A weebtrees(https://webtrees.net) 2.1 custom module to download GEDCOM files on URL requests 
 * with the tree name, GEDCOM file name and authorization provided as parameters within the URL.
 * 
 * Example URL:
 * http://MY_URL/webtrees/index.php?route=/webtrees/DownloadGedcomWithURL/tree/MY_TREE/filename/MY_FILENAME/privacy/MY_PRIVACY_LEVEL
 *
 * MY_TREE specifies the webtrees tree name
 *
 * M>_FILENAME has to be provided without .ged extension
 * i.e. use my_file instead of my_file.ged
 *
 * For MY_PRIVACY_LEVEL, the following values can be used
 *  	gedadmin
 * 		user 
 * 		visitor  
 *		none     (Default)
 *
 * Note:
 * The Gedcom file will always be downloaded from the last tree, which was used
 * in the frontend.
 * 
 * IMPORTANT SECURITY NOTE:
 * Please note that installing this module will enable everyone who can reach the
 * webtrees URL to download the GEDCOM files from webtrees. This even works if no user
 * is logged in. Therefore, you should only consider to use this module in private 
 * networks etc.
 *
 */

 
declare(strict_types=1);

namespace DownloadGedcomWithURLNamespace;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Validator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function route;



class DownloadGedcomWithURL extends AbstractModule implements ModuleCustomInterface, RequestHandlerInterface {

    use ModuleCustomTrait;
 
    protected const ROUTE_URL = '/DownloadGedcomWithURL/tree/{tree}/filename/{filename}/privacy/{privacy}'; 

    private GedcomExportService $gedcom_export_service;


   /**
     * DownloadGedcomWithURL constructor.
     *
     * @param ChartService $chart_service
     */
    public function __construct()
    {
	$response_factory = app(ResponseFactoryInterface::class);
        $stream_factory = new Psr17Factory();

        $this->gedcom_export_service = new GedcomExportService($response_factory, $stream_factory);
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        Registry::routeFactory()->routeMap()
            ->get(static::class, static::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);
    }
	
    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */	 
    public function title(): string
    {
        return 'DownloadGedcomWithURL custom module';
    }

     /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree         = Validator::attributes($request)->tree();
        $filename     = Validator::attributes($request)->string('filename');
        $format       = 'gedcom';
        $privacy      = Validator::attributes($request)->isInArray(['none', 'gedadmin', 'user', 'visitor'])->string('privacy');
        $encoding     = UTF8::NAME;
        $line_endings = 'CRLF';

        return $this->gedcom_export_service->downloadResponse($tree, true, $encoding, $privacy, $line_endings, $filename, $format);
    }

}
