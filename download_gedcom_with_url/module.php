<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2021 webtrees development team
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
 * A middleware module to download GEDCOM files on URL requests 
 * with the GEDCOM file name and authorization provided as URL parameters
 * 
 * Example URL:
 * http://SOME_URL/webtrees/index.php?route=SOME_ROUTE&downloadgedcom=FILENAME&accesslevel=ACCESS_LEVEL
 *
 * FILENAME has to be provided without .ged extension
 * i.e. use my_file instead of my_file.ged
 *
 * For ACCESS_LEVEL, the following values can be used
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
 * webtrees URLs to download the GEDCOM files from webtrees. This even works if no user
 * is logged in. Therefore, you should only consider to use this module in private 
 * networks etc.
 *
 */
 

declare(strict_types=1);

namespace DownloadGedcomWithURLNamespace;

require __DIR__ . '/DownloadGedcomWithURL.php';

return new DownloadGedcomWithURL();