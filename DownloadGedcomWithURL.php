<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 *                    <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):
 * Copyright (C) 2022 Carmen Just
 *                    <https://justcarmen.nl>
 *
 * DownloadGedcomWithURL (webtrees custom module):
 * Copyright (C) 2023 Markus Hemprich
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

use Cissee\WebtreesExt\MoreI18N;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Encodings\ANSEL;
use Fisharebest\Webtrees\Encodings\ASCII;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\Windows1252;
use Fisharebest\Webtrees\Exceptions\FileUploadException;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\GedcomFilters\GedcomEncodingFilter;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageTrees;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Module\ModuleDataFixTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use ErrorException;
use Throwable;

use ReflectionClass;
use stdClass;

use function substr;


class DownloadGedcomWithURL extends AbstractModule implements 
	ModuleCustomInterface, 
	ModuleConfigInterface,
	RequestHandlerInterface,
    ModuleDataFixInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleDataFixTrait;

    //The data fix service
    private DataFixService $data_fix_service;

    //The tree service
    private TreeService $tree_service;    

    //The Gedcom filter Service 
    private FilteredGedcomExportService $filtered_gedcom_export_service;

    //A stream factory
    private StreamFactoryInterface $stream_factory;

    //The root file system
    private FilesystemOperator $root_filesystem;

    //A set of patterns for tag combinations, which has already been matched in a data fix
    private array $matched_pattern_for_tag_combination_in_data_fix;

    //A set of Gedcom filters, which is used in the data fix
    private array $gedcom_filters_in_data_fix;

	//Custom module version
	public const CUSTOM_VERSION = '3.2.4';

	//Routes
	protected const ROUTE_REMOTE_ACTION = '/DownloadGedcomWithURL';
	protected const ROUTE_EXPORT_PAGE   = '/ExtendedGedcomExport';
	protected const ROUTE_IMPORT_PAGE   = '/ExtendedGedcomImport';
	protected const ROUTE_CONVERT_PAGE   = '/ExtendedGedcomConvert';
	protected const ROUTE_SETTINGS_PAGE   = '/ExtendedGedcomImportExportSettings';

	//Github repository
	public const GITHUB_REPO = 'Jefferson49/DownloadGedcomWithURL';

	//Github API URL to get the information about the latest releases
	public const GITHUB_API_LATEST_VERSION = 'https://api.github.com/repos/'. self::GITHUB_REPO . '/releases/latest';
	public const GITHUB_API_TAG_NAME_PREFIX = '"tag_name":"v';

	//Author of custom module
	public const CUSTOM_AUTHOR = 'Markus Hemprich';

    //Strings cooresponding to variable names
    public const VAR_GEDOCM_FILTER = 'gedcom_filter';
    public const VAR_GEDCOM_FILTER_LIST = 'gedcom_filter_list';
    public const VAR_DATA_FIX_TYPES = 'types';

    //Prefences, Settings
	public const PREF_MODULE_VERSION = 'module_version';
    public const PREF_DEFAULT_TREE_NAME = 'default_tree_name';
	public const PREF_SECRET_KEY = "secret_key";
	public const PREF_USE_HASH = "use_hash";
	public const PREF_ALLOW_REMOTE_DOWNLOAD = "allow_remote_download";
	public const PREF_ALLOW_REMOTE_UPLOAD = "allow_remote_upload";
	public const PREF_ALLOW_REMOTE_SAVE = "allow_remote_save";
	public const PREF_ALLOW_REMOTE_CONVERT = "allow_remote_convert";
	public const PREF_FOLDER_TO_SAVE = "folder_to_save";
    public const PREF_DEFAULT_GEDCOM_FILTER1 = 'default_gedcom_filter1';
    public const PREF_DEFAULT_GEDCOM_FILTER2 = 'default_gedcom_filter2';
    public const PREF_DEFAULT_GEDCOM_FILTER3 = 'default_gedcom_filter3';
    public const PREF_DEFAULT_PRIVACY_LEVEL = 'default_privacy_level'; 
    public const PREF_DEFAULT_EXPORT_FORMAT = 'default_export_format';
    public const PREF_DEFAULT_ENCODING = 'default_encoding';
    public const PREF_DEFAULT_ENDING = 'default_ending';
    public const PREF_DEFAULT_TIME_STAMP = 'default_time_stamp';
    public const PREF_DEFAULT_GEDCOM_VERSION = 'default_gedcom_version';
    public const PREF_DEFAULT_GEDCOM_L_SELECTION = 'default_gedcom_l_selection';
    
    //Actions
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_SAVE     = 'save';
    public const ACTION_BOTH     = 'both';
    public const ACTION_UPLOAD   = 'upload';
    public const ACTION_CONVERT  = 'convert';
    public const CALLED_FROM_CONTROL_PANEL = "called_from_control_panel";

    //Time stamp values
    public const TIME_STAMP_PREFIX  = 'prefix';
    public const TIME_STAMP_POSTFIX = 'postfix';
    public const TIME_STAMP_NONE    = 'none';

	//Alert tpyes
	public const ALERT_DANGER = 'alert_danger';
	public const ALERT_SUCCESS = 'alert_success';

    //Maximum level of includes for Gedcom filters
    private const MAXIMUM_FILTER_INCLUDE_LEVELS = 10;

    //Old preferences    
	public const PREF_ALLOW_DOWNLOAD = "allow_download";
    public const PREF_DEFAULT_FiLE_NAME = 'default_file_name';
    public const PREF_CONTROL_PANEL_SECRET_KEY = 'control_panel_secret_key';
    public const PREF_DEFAULT_ACTION = 'default_action';



   /**
     * DownloadGedcomWithURL constructor.
     */
    public function __construct()
    {
	    $response_factory = app(ResponseFactoryInterface::class);
        $this->stream_factory = new Psr17Factory();
        $this->data_fix_service = New DataFixService();
        $this->tree_service   = new TreeService(new GedcomImportService);
        $this->filtered_gedcom_export_service = new FilteredGedcomExportService($response_factory, $this->stream_factory);
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        //Initialize variables
        $this->matched_pattern_for_tag_combination_in_data_fix = [];
        $this->gedcom_filters_in_data_fix = [];
        $this->gedcom_filters_loaded_in_data_fix = false;
        $this->root_filesystem = Registry::filesystem()->root();

        $router = Registry::routeFactory()->routeMap();            

        //Register a route for remote requests
        $router
            ->get(static::class, self::ROUTE_REMOTE_ACTION, $this)
            ->allows(RequestMethodInterface::METHOD_POST);
            
        //Register a route for the settings view
        $router
            ->get(SettingsPage::class, self::ROUTE_SETTINGS_PAGE)
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the import view
        $router
            ->get(ImportGedcomPage::class, self::ROUTE_IMPORT_PAGE)
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the export view
        $router
            ->get(ExportGedcomPage::class, self::ROUTE_EXPORT_PAGE)
            ->allows(RequestMethodInterface::METHOD_POST);

            //Register a route for the convert view
        $router
            ->get(ConvertGedcomPage::class, self::ROUTE_CONVERT_PAGE)
            ->allows(RequestMethodInterface::METHOD_POST);

		// Register a namespace for the views.
		View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
	
    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return I18N::translate('Extended Import/Export');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::description()
     */
    public function description(): string
    {
        /* I18N: Description of the “AncestorsChart” module */
        return I18N::translate('A custom module for advanced GEDCOM import, export, and filter operations. The module also supports remote downloads/uploads/filters via URL requests.');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::resourcesFolder()
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * Get the active module name, e.g. the name of the currently running module
     *
     * @return string
     */
    public static function activeModuleName(): string
    {
        return '_' . basename(__DIR__) . '_';
    }
    
    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleLatestVersion()
     */
    public function customModuleLatestVersion(): string
    {
        // No update URL provided.
        if (self::GITHUB_API_LATEST_VERSION === '') {
            return $this->customModuleVersion();
        }
        return Registry::cache()->file()->remember(
            $this->name() . '-latest-version',
            function (): string {
                try {
                    $client = new Client(
                        [
                        'timeout' => 3,
                        ]
                    );

                    $response = $client->get(self::GITHUB_API_LATEST_VERSION);

                    if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                        $content = $response->getBody()->getContents();
                        preg_match_all('/' . self::GITHUB_API_TAG_NAME_PREFIX . '\d+\.\d+\.\d+/', $content, $matches, PREG_OFFSET_CAPTURE);

						if(!empty($matches[0]))
						{
							$version = $matches[0][0][0];
							$version = substr($version, strlen(self::GITHUB_API_TAG_NAME_PREFIX));	
						}
						else
						{
							$version = $this->customModuleVersion();
						}

                        return $version;
                    }
                } catch (GuzzleException $ex) {
                    // Can't connect to the server?
                }

                return $this->customModuleVersion();
            },
            86400
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_REPO;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $language
     *
     * @return array
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customTranslations()
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * View module settings in control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        //Check update of module version
        $this->checkModuleVersionUpdate();
        
        $this->layout = 'layouts/administration';       

        //Load Gedcom filters
        try {
            self::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }
        
        $tree_list = $this->getTreeNameTitleList();

        //Check the Gedcom filters, which are defined in the prefernces
        $this->checkFilterPreferences(self::PREF_DEFAULT_GEDCOM_FILTER1);
        $this->checkFilterPreferences(self::PREF_DEFAULT_GEDCOM_FILTER2);
        $this->checkFilterPreferences(self::PREF_DEFAULT_GEDCOM_FILTER3);

        $data_folder = str_replace('\\', '/', Registry::filesystem()->dataName());
		$root_folder = str_replace('\\', '/', Registry::filesystem()->rootName());
		$data_folder_relative = str_replace($root_folder, '', $data_folder);

        $default_tree_name = $this->getPreference(self::PREF_DEFAULT_TREE_NAME, '');
        
        if (!array_key_exists($default_tree_name, $tree_list)) {
            $default_tree_name = array_key_first($tree_list);
        }

        $default_tree      = $this->tree_service->all()[$default_tree_name];

        return $this->viewResponse(
            $this->name() . '::settings',
            [
                'title'                               => $this->title(),
                self::PREF_DEFAULT_TREE_NAME          => $default_tree->name(),
                'tree_list'                           => $tree_list,
                self::VAR_GEDCOM_FILTER_LIST          => $this->getGedcomFilterList(),
				self::PREF_SECRET_KEY                 => $this->getPreference(self::PREF_SECRET_KEY, ''),
				self::PREF_CONTROL_PANEL_SECRET_KEY   => $this->getPreference(self::PREF_CONTROL_PANEL_SECRET_KEY, ''),
				self::PREF_USE_HASH                   => boolval($this->getPreference(self::PREF_USE_HASH, '1')),
				self::PREF_ALLOW_REMOTE_DOWNLOAD      => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, '0')),
				self::PREF_ALLOW_REMOTE_UPLOAD        => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_UPLOAD, '0')),
				self::PREF_ALLOW_REMOTE_SAVE          => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_SAVE, '0')),
				self::PREF_ALLOW_REMOTE_CONVERT       => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_CONVERT, '0')),
				self::PREF_FOLDER_TO_SAVE             => $this->getPreference(self::PREF_FOLDER_TO_SAVE, $data_folder_relative),
                self::PREF_DEFAULT_GEDCOM_FILTER1     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER1, ''),
                self::PREF_DEFAULT_GEDCOM_FILTER2     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER2, ''),
                self::PREF_DEFAULT_GEDCOM_FILTER3     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER3, ''),
                self::PREF_DEFAULT_PRIVACY_LEVEL      => $this->getPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, 'none'),
                self::PREF_DEFAULT_EXPORT_FORMAT      => $this->getPreference(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom'),
                self::PREF_DEFAULT_ENCODING           => $this->getPreference(self::PREF_DEFAULT_ENCODING, UTF8::NAME),
                self::PREF_DEFAULT_ENDING             => $this->getPreference(self::PREF_DEFAULT_ENDING, 'CRLF'),
                self::PREF_DEFAULT_TIME_STAMP         => $this->getPreference(self::PREF_DEFAULT_TIME_STAMP, self::TIME_STAMP_NONE),
                self::PREF_DEFAULT_GEDCOM_VERSION     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_VERSION, '0'),
                self::PREF_DEFAULT_GEDCOM_L_SELECTION => $this->getPreference(self::PREF_DEFAULT_GEDCOM_L_SELECTION, '0'),
            ]
        );
    }

    /**
     * Save module settings after returning from control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $save                       = Validator::parsedBody($request)->string('save', '');
        $use_hash                   = Validator::parsedBody($request)->boolean(self::PREF_USE_HASH, false);
        $use_hash                   = Validator::parsedBody($request)->boolean(self::PREF_USE_HASH, false);
        $allow_remote_download      = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_DOWNLOAD, false);
        $allow_remote_upload        = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_UPLOAD, false);
        $allow_remote_save          = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_SAVE, false);
        $allow_remote_convert       = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_CONVERT, false);
        $new_secret_key             = Validator::parsedBody($request)->string('new_secret_key', '');
        $folder_to_save             = Validator::parsedBody($request)->string(self::PREF_FOLDER_TO_SAVE, Site::getPreference('INDEX_DIRECTORY'));
        $default_tree_name          = Validator::parsedBody($request)->string(self::PREF_DEFAULT_TREE_NAME, '');
        $default_gedcom_filter1     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_FILTER1, '');
        $default_gedcom_filter2     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_FILTER2, '');
        $default_gedcom_filter3     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_FILTER3, '');
        $default_privacy_level      = Validator::parsedBody($request)->string(self::PREF_DEFAULT_PRIVACY_LEVEL, 'none');
        $default_export_format      = Validator::parsedBody($request)->string(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom');
        $default_encoding           = Validator::parsedBody($request)->string(self::PREF_DEFAULT_ENCODING, UTF8::NAME);
        $default_ending             = Validator::parsedBody($request)->string(self::PREF_DEFAULT_ENDING, 'CRLF');
        $default_time_stamp         = Validator::parsedBody($request)->string(self::PREF_DEFAULT_TIME_STAMP, self::TIME_STAMP_NONE);
        $default_gedcom_version     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_VERSION, '0');
        $default_gedcom_l_selection = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_L_SELECTION, '0');
        
        //Save the received settings to the user preferences
        if ($save === '1') {

            $new_key_error = false;

            //If no new secret key is provided
			if($new_secret_key === '') {
				//If use hash changed from true to false, reset key (hash cannot be used any more)
				if(boolval($this->getPreference(self::PREF_USE_HASH, '0')) && !$use_hash) {
					$this->setPreference(self::PREF_SECRET_KEY, '');
				}
				//If use hash changed from false to true, take old key (for planned encryption) and save as hash
				elseif(!boolval($this->getPreference(self::PREF_USE_HASH, '0')) && $use_hash) {
					$new_secret_key = $this->getPreference(self::PREF_SECRET_KEY, '');
                    $hash_value = password_hash($new_secret_key, PASSWORD_BCRYPT);
                    $this->setPreference(self::PREF_SECRET_KEY, $hash_value);
				}
                //If no new secret key and no changes in hashing, do nothing
			}
			//If new secret key is too short
			elseif(strlen($new_secret_key)<8) {
				$message = I18N::translate('The provided secret key is too short. Please provide a minimum length of 8 characters.');
				FlashMessages::addMessage($message, 'danger');
                $new_key_error = true;				
			}
			//If new secret key does not escape correctly
			elseif($new_secret_key !== e($new_secret_key)) {
				$message = I18N::translate('The provided secret key contains characters, which are not accepted. Please provide a different key.');
				FlashMessages::addMessage($message, 'danger');				
                $new_key_error = true;		
            }
			//If new secret key shall be stored with a hash, create and save hash
			elseif($use_hash) {
				$hash_value = password_hash($new_secret_key, PASSWORD_BCRYPT);
				$this->setPreference(self::PREF_SECRET_KEY, $hash_value);
			}
            //Otherwise, simply store the new secret key
			else {
				$this->setPreference(self::PREF_SECRET_KEY, $new_secret_key);
			}

			//Check and set folder to save
			if (substr_compare($folder_to_save, '/', -1, 1) !== 0) {
				$folder_to_save .= '/';
			}
            
			if (substr_compare($folder_to_save, '/', 0, 1) === 0) {
				$folder_to_save = substr($folder_to_save, 1,null);
			}

            if ($folder_to_save === '') {
                $folder_to_save = '/';
            }

			if (is_dir($folder_to_save)) {
				$this->setPreference(self::PREF_FOLDER_TO_SAVE, $folder_to_save);
			} else {
				FlashMessages::addMessage(I18N::translate('The folder settings could not be saved, because the folder “%s” does not exist.', e($folder_to_save)), 'danger');
			}

            //Save settings to preferences
            if(!$new_key_error) {
                $this->setPreference(self::PREF_USE_HASH, $use_hash ? '1' : '0');
            }

            //Save settingss
			$this->setPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, $allow_remote_download ? '1' : '0');
			$this->setPreference(self::PREF_ALLOW_REMOTE_UPLOAD, $allow_remote_upload ? '1' : '0');
			$this->setPreference(self::PREF_ALLOW_REMOTE_SAVE, $allow_remote_save ? '1' : '0');
			$this->setPreference(self::PREF_ALLOW_REMOTE_CONVERT, $allow_remote_convert ? '1' : '0');

            //Save default settings to preferences
            $this->setPreference(self::PREF_DEFAULT_TREE_NAME, $default_tree_name);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_FILTER1, $default_gedcom_filter1);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_FILTER2, $default_gedcom_filter2);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_FILTER3, $default_gedcom_filter3);
            $this->setPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, $default_privacy_level);
            $this->setPreference(self::PREF_DEFAULT_EXPORT_FORMAT, $default_export_format);
            $this->setPreference(self::PREF_DEFAULT_ENCODING, $default_encoding);
            $this->setPreference(self::PREF_DEFAULT_ENDING, $default_ending);
            $this->setPreference(self::PREF_DEFAULT_TIME_STAMP, $default_time_stamp);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_VERSION, $default_gedcom_version);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_L_SELECTION, $default_gedcom_l_selection);

            //Finally, show a success message
			$message = I18N::translate('The preferences for the module "%s" were updated.', $this->title());
			FlashMessages::addMessage($message, 'success');	
		}

        return redirect($this->getConfigLink());
    }

    /**
     * Check if module version is new and start update activities if needed
     *
     * @return void
     */
    public function checkModuleVersionUpdate(): void
    {
        $sucessful_update = false;

        //If secret key is already stored and secret key hashing preference is not available (i.e. before module version v3.0.1) 
        if($this->getPreference(self::PREF_SECRET_KEY, '') !== '' && $this->getPreference(self::PREF_USE_HASH, '') === '') {

			//Set secret key hashing to false
			$this->setPreference(self::PREF_USE_HASH, '0');

            $sucessful_update = true;
		}

        //Update remote preferences
        if ($this->getPreference(self::PREF_ALLOW_DOWNLOAD, '') !== '') {

            //Migrate old preference value to new preferences
            $this->setPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, self::PREF_ALLOW_DOWNLOAD);
            $this->setPreference(self::PREF_ALLOW_REMOTE_SAVE, self::PREF_ALLOW_DOWNLOAD);

            //Delete old preference value, i.e. set to ''
            $this->setPreference(self::PREF_CONTROL_PANEL_SECRET_KEY, '');      
            $this->setPreference(self::PREF_ALLOW_DOWNLOAD, '');      
            $sucessful_update = true;      
        }

        //Remove old preferences
        if ($this->getPreference(self::PREF_DEFAULT_FiLE_NAME, '') !== '') {
            $this->setPreference(self::PREF_DEFAULT_FiLE_NAME, '');
            $this->setPreference(self::PREF_DEFAULT_ACTION, '');
        }    

        //Update custom module version if changed
        if($this->getPreference(self::PREF_MODULE_VERSION, '') !== self::CUSTOM_VERSION) {
            $this->setPreference(self::PREF_MODULE_VERSION, self::CUSTOM_VERSION);
        }

        if ($sucessful_update) {
            //Show flash message for update of preferences
            $message = I18N::translate('The preferences for the custom module "%s" were sucessfully updated to the new module version %s.', $this->title(), self::CUSTOM_VERSION);
            FlashMessages::addMessage($message, 'success');	
        }        
    }

    /**
     * Check if an Gedcom filter is available. If not, reset Gedcom filter to none
     *
     * @param string          $preference_name     The preference name of an Gedcom filter
     * 
     * @return string                              The class name of the Gedcom filter
     */
    private function checkFilterPreferences(string $preference_name): string {

        //Get a list with the class names of all available Gedcom filters
        $gedcom_filter_list = $this->getGedcomFilterList();

        //Filter name from preferences
        $gedcom_filter_class_name = $this->getPreference($preference_name);

        //If currently selected Gedcom filter is not in the available filter list, reset Gedcom filter to none
        if (!array_key_exists($gedcom_filter_class_name, $gedcom_filter_list)) {

            //Reset preference
            $this->setPreference($preference_name, '');

            //Create flash message
            $message = I18N::translate('The preferences for the default GEDCOM filter were reset to "none", because the selected GEDCOM filter %s could not be found', $gedcom_filter_class_name);
            FlashMessages::addMessage($message, 'danger');

            $gedcom_filter_class_name = '';
        }
 
        //Validate the Gedcom filter
        if ($gedcom_filter_class_name !== '' && ($error = $this->validateGedcomFilter($gedcom_filter_class_name)) !== '') {
    
            FlashMessages::addMessage($error, 'danger');
        }
        
        return $gedcom_filter_class_name;
    }

    /**
     * All the trees that the current user has permission to access.
     *
     * @return Collection<array-key,Tree>
     */
    public function all(): Collection
    {
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
     /**
     * Check if tree is a valid tree
     *
     * @return bool
     */ 
     private function isValidTree(string $tree_name): bool
	 {
		$find_tree = $this->all()->first(static function (Tree $tree) use ($tree_name): bool {
            return $tree->name() === $tree_name;
        });
		
		$is_valid_tree = $find_tree instanceof Tree;
		
		return $is_valid_tree;
	 }
	 
	 /**
     * Show error message in the front end
     *
     * @return ResponseInterface
     */ 
     public function showErrorMessage(string $text): ResponseInterface
	 {		
		return $this->viewResponse($this->name() . '::alert', [
            'title'        	=> 'Error',
			'tree'			=> null,
			'alert_type'    => DownloadGedcomWithURL::ALERT_DANGER,
			'module_name'	=> $this->title(),
			'text'  	   	=> $text,
		]);	 
	 }
 
	 /**
     * Show success message in the front end
     *
     * @return ResponseInterface
     */ 
	public function showSuccessMessage(string $text): ResponseInterface
	{		
	   return $this->viewResponse($this->name() . '::alert', [
		   'title'        	=> 'Success',
		   'tree'			=> null,
		   'alert_type'     => DownloadGedcomWithURL::ALERT_SUCCESS,
		   'module_name'	=> $this->title(),
		   'text'  	   	    => $text,
	   ]);	 
	}

	 /**
     * Get an array [name => title] for all trees 
     *
     * @return array error message
     */ 
     public function getTreeNameTitleList(): array {

        $tree_list = [];
        $all_trees = $this->all();

        foreach($all_trees as $tree) {
            $tree_list[$tree->name()] = $tree->name() . ' (' . $tree->title() . ')';
        }    

        return $tree_list;
     }

	 /**
     * Load classes for Gedcom filters
     *
     * @return string error message
     */ 

     public static function loadGedcomFilterClasses(): string {

        $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';
        $filter_files = scandir(dirname(__FILE__) . "/resources/filter/");

        $onError = function ($level, $message, $file, $line) {
            throw new ErrorException($message, 0, $level, $file, $line);
        };

        foreach ($filter_files as $file) {
            if (substr_compare($file, '.php', -4, 4) === 0) {

                $class_name = str_replace('.php', '', $file);

                if (!class_exists($name_space . $class_name)) {
                    try {
                        set_error_handler($onError);
                        require __DIR__ . '/resources/filter/' . $file;
                    }
                    catch (Throwable $th) {
                        throw new DownloadGedcomWithUrlException(I18N::translate('A compilation error was detected in the following GEDCOM filter') . ': ' . 
                        __DIR__ . '/resources/filter/' . $file . ', ' . I18N::translate('line') . ': ' . $th-> getLine() . ', ' . I18N::translate('error message') . ': ' . $th->getMessage());
                    }
                    finally {
                        restore_error_handler();
                    }        
                }
            }
        };

        return '';
    }

	 /**
     * Get all available Gedcom filters
     *
     * @return array<string>  An array with the class names all available Gedcom filters
     */ 

     public function getGedcomFilterList(): array {

        $gedcom_filter_list =[
            ''             => MoreI18N::xlate('None'),
        ];

        foreach (get_declared_classes() as $class_name) {

            $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';

            if (strpos($class_name, $name_space) !==  false) {

                if (in_array($name_space . 'GedcomFilterInterface', class_implements($class_name))) {

                    $object = new $class_name();
                    $class_name = str_replace($name_space, '', $class_name);

                    if ($class_name !== 'AbstractGedcomFilter') $gedcom_filter_list[$class_name] = $object->name();
                }
            }
        }

        return $gedcom_filter_list;
    }

	/**
     * Validate Gedcom filter
     *
     * @param $gedcom_filter_name
     * 
     * @return string error message
     */ 

    private function validateGedcomFilter($gedcom_filter_name): string {

        //Check if Gedcom filter class is valid
        $gedcom_filter_class_name = __NAMESPACE__ . '\\' . $gedcom_filter_name;

        if (!class_exists($gedcom_filter_class_name) OR !(new $gedcom_filter_class_name() instanceof GedcomFilterInterface)) {

            return I18N::translate('The GEDCOM filter was not found') . ': ' . $gedcom_filter_name;
        }

        $gedcom_filter_instance = new $gedcom_filter_class_name();

        //Validate the content of the Gedcom filter
        $error = $gedcom_filter_instance->validate();

        if ($error !== '') {
            return $error;
        }

        return '';
    }

    /**
     * Check whether further filters are included in a list of Gedcom filters and add to Gedcom filter list accordingly
     *
     * @param array $gedcom_filter_set   A set of (already inlcuded) Gedcom filters
     * @param array $additional_filters  A set of Gedcom filters to be checked and included
     * @param array $include_structure   A hierarchical list of included Gedcom filters to check loops etc.
     * 
     * @return array 
     */ 

    private function addIncludedGedcomFilters(array $gedcom_filter_set, array $additional_filters, array $include_structure): array {

        while (sizeof($additional_filters) > 0) {

            //Get first item of Gedcom filter set and remove it from additional filter list
            $gedcom_filter = array_shift($additional_filters);

            //Add Gedcom filter to include structure
            $include_structure[] = $gedcom_filter;

            //Error if size of include structure exceeds maximum level
            if (sizeof($include_structure) > self::MAXIMUM_FILTER_INCLUDE_LEVELS) {

                $error = I18N::translate('The include hierarchy for GEDCOM filters exceeds the maximum level of %s includes.', (string) self::MAXIMUM_FILTER_INCLUDE_LEVELS);

                if (in_array($gedcom_filter, $include_structure)) {

                    $error .= ' ' . I18N::translate('The following GEDCOM filter might cause a loop in the include structure, because it was detected more than once in the include hierarchy') . ': ' . (new ReflectionClass($gedcom_filter))->getShortName();
                }
                else {
                    $error .= ' ' . I18N::translate('Please check the include structure of the selected GEDCOM filters.');
                }

                throw new DownloadGedcomWithUrlException($error);
            }
            
            if ($gedcom_filter !== null) {

                //Add include filters before
                $gedcom_filter_set = array_merge($gedcom_filter_set, $this->addIncludedGedcomFilters([], $gedcom_filter->getIncludedFiltersBefore(), $include_structure));
                //Add filter
                array_push($gedcom_filter_set, $gedcom_filter);
                //Add include filters after
                $gedcom_filter_set = array_merge($gedcom_filter_set, $this->addIncludedGedcomFilters([], $gedcom_filter->getIncludedFiltersAfter(), $include_structure));
            }
        }    

        return $gedcom_filter_set;
    }

    /**
     * Get the namespace for the views
     *
     * @return string
     */
    public static function viewsNamespace(): string
    {
        return '_' . basename(__DIR__) . '_';
    }

    /**
     * {@inheritDoc}
     *
     * @param Tree $tree
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::fixOptions()
     */
    public function fixOptions(Tree $tree): string
    {   
        //Reset matched patterns at the start of the data fix 
        $this->matched_pattern_for_tag_combination_in_data_fix = [];

        //Load Gedcom filters
        try {
            self::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }

        return view(
            self::viewsNamespace() . '::options',
            [
                self::VAR_GEDCOM_FILTER_LIST    => $this->getGedcomFilterList(),
                self::VAR_GEDOCM_FILTER . '1'   => '',
                self::VAR_GEDOCM_FILTER . '2'   => '',
                self::VAR_GEDOCM_FILTER . '3'   => '',
            ]
        );
    }

    /**
     * A list of all records that need examining.  This may include records
     * that do not need updating, if we can't detect this quickly using SQL.
     *
     * @param Tree                 $tree
     * @param array<string,string> $params
     *
     * @return Collection<int,object>
     */
    public function recordsToFix(Tree $tree, array $params): Collection
    {
        $families     = $this->familiesToFixQuery($tree, $params)->pluck('f_id');
        $individuals  = $this->individualsToFixQuery($tree, $params)->pluck('i_id');
        $locations    = $this->locationsToFixQuery($tree, $params)->pluck('o_id');
        $media        = $this->mediaToFixQuery($tree, $params)->pluck('m_id');
        $notes        = $this->notesToFixQuery($tree, $params)->pluck('o_id');
        $repositories = $this->repositoriesToFixQuery($tree, $params)->pluck('o_id');
        $sources      = $this->sourcesToFixQuery($tree, $params)->pluck('s_id');
        $submitters   = $this->submittersToFixQuery($tree, $params)->pluck('o_id');

        $header = new stdClass;
        $header->xref = 'HEAD';
        $header->type = 'HEAD';

        $records = new Collection([$header]);
        
        if ($families !== null) {
            $records = $records->concat($this->mergePendingRecords($families, $tree, Family::RECORD_TYPE));
        }

        if ($individuals !== null) {
            $records = $records->concat($this->mergePendingRecords($individuals, $tree, Individual::RECORD_TYPE));
        }

        if ($locations !== null) {
            $records = $records->concat($this->mergePendingRecords($locations, $tree, Location::RECORD_TYPE));
        }

        if ($media !== null) {
            $records = $records->concat($this->mergePendingRecords($media, $tree, Media::RECORD_TYPE));
        }

        if ($notes !== null) {
            $records = $records->concat($this->mergePendingRecords($notes, $tree, Note::RECORD_TYPE));
        }

        if ($repositories !== null) {
            $records = $records->concat($this->mergePendingRecords($repositories, $tree, Repository::RECORD_TYPE));
        }

        if ($sources !== null) {
            $records = $records->concat($this->mergePendingRecords($sources, $tree, Source::RECORD_TYPE));
        }

        if ($submitters !== null) {
            $records = $records->concat($this->mergePendingRecords($submitters, $tree, Submitter::RECORD_TYPE));
        }

        $trailer = new stdClass;
        $trailer->xref = 'TRLR';
        $trailer->type = 'TRLR';

        $records = $records->concat([$trailer]);

        return $records
            ->unique()
            ->sort(static function (object $x, object $y) {
                return $x->xref <=> $y->xref;
            });
    }

    /**
     * {@inheritDoc}
     *
     * @param GedcomRecord $record
     * @param array        $params
     *
     * @return bool
     *
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::doesRecordNeedUpdate()
     */
    public function doesRecordNeedUpdate(GedcomRecord $record, array $params): bool
    {
        //Get filters, if not yet available
        if ($this->gedcom_filters_in_data_fix === []) {
            $this->gedcom_filters_in_data_fix = $this->getGedcomFiltersFromParams($params);
        }

        if ($this->gedcom_filters_in_data_fix === []) return false;

        $gedcom = $record->gedcom();
        $filtered_records = $this->filtered_gedcom_export_service->applyGedcomFilters([$gedcom], $this->gedcom_filters_in_data_fix, $this->matched_pattern_for_tag_combination_in_data_fix, $record->tree());

        $old = $gedcom . "\n";
        $new = $filtered_records[0] ?? $gedcom;

        return $new !== $old;
    }

    /**
     * {@inheritDoc}
     *
     * @param GedcomRecord $record
     * @param array        $params
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::previewUpdate()
     */
    public function previewUpdate(GedcomRecord $record, array $params): string
    {
        $gedcom = $record->gedcom();
        $filtered_records = $this->filtered_gedcom_export_service->applyGedcomFilters([$gedcom], $this->gedcom_filters_in_data_fix, $this->matched_pattern_for_tag_combination_in_data_fix, $record->tree());

        $old = $gedcom;
        $new = $filtered_records[0] ?? '';

        return $this->data_fix_service->gedcomDiff($record->tree(), $old, $new);
    }

    /**
     * {@inheritDoc}
     *
     * @param GedcomRecord $record
     * @param array        $params
     *
     * @return void
     *
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::updateRecord()
     */
    public function updateRecord(GedcomRecord $record, array $params): void
    {
        $gedcom = $record->gedcom();
        $filtered_records = $this->filtered_gedcom_export_service->applyGedcomFilters([$gedcom], $this->gedcom_filters_in_data_fix, $this->matched_pattern_for_tag_combination_in_data_fix, $record->tree());

        $new = $filtered_records[0] ?? $gedcom;  
        $record->updateRecord($new, false);

        return;
    }    

	/**
     * Get Gedcom filters from data fix params
     * 
     * @param array $params                    Params of a data fix          
     * 
     * @return array<GedcomFilterInterface>    A set of Gedcom filters
     */	
    public function getGedcomFiltersFromParams(array $params): array    
    {
        $gedcom_filters = [];

        foreach ($params as $key => $gedcom_filter_name) {

            if (strpos($key, self::VAR_GEDOCM_FILTER) !== false && $gedcom_filter_name !== '') {
                $gedcom_filters[] = $gedcom_filter_name;
            }
        }

        $gedcom_filter1 = $gedcom_filters[0] ?? '';
        $gedcom_filter2 = $gedcom_filters[1] ?? '';
        $gedcom_filter3 = $gedcom_filters[2] ?? '';

        //Load Gedcom filters
        try {
            self::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }

        try {
            $gedcom_filters = $this->createGedcomFilterList($gedcom_filter1, $gedcom_filter2, $gedcom_filter3);
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }
        
        return $gedcom_filters;
    }

	/**
     * Create a list of Gedcom filters from filter names; also handle include structure of the filters
     * 
     * @param string $gedcom_filter1            Name of Gedcom filter 1
     * @param string $gedcom_filter2            Name of Gedcom filter 2
     * @param string $gedcom_filter3            Name of Gedcom filter 3
     *
     * @return array<GedcomFilterInterface> 	A set of Gedcom filters
     */	
    public function createGedcomFilterList(string $gedcom_filter1, string $gedcom_filter2, string $gedcom_filter3): array    
    {
        //Add namespace to Gedcom filters
        $gedcom_filter_class_name1 = __NAMESPACE__ . '\\' . $gedcom_filter1;
        $gedcom_filter_class_name2 = __NAMESPACE__ . '\\' . $gedcom_filter2;
        $gedcom_filter_class_name3 = __NAMESPACE__ . '\\' . $gedcom_filter3;

        //Get instances of Gedcom filters
        $gedcom_filter_instance1 = $gedcom_filter1 !== '' ? new $gedcom_filter_class_name1() : null;
        $gedcom_filter_instance2 = $gedcom_filter2 !== '' ? new $gedcom_filter_class_name2() : null;
        $gedcom_filter_instance3 = $gedcom_filter3 !== '' ? new $gedcom_filter_class_name3() : null;

        //Create set of Gedcom filters
        $filters_to_add = [
            $gedcom_filter_instance1,
            $gedcom_filter_instance2,
            $gedcom_filter_instance3,
        ];

        //Add Gedcom filters, which might also add further Gedcom filters from their include lists
        $gedcom_filter_set = $this->addIncludedGedcomFilters([], $filters_to_add, []);

        return $gedcom_filter_set;
    }

    /**
     * Import data from a gedcom file to an array of Gedcom record strings.
     *
     * @param StreamInterface      $stream   The GEDCOM file.
     * @param string               $encoding Override the encoding specified in the header.
     *
     * @return array<string>                 A set of Gedcom record strings
     */
    public function importGedcomFile(StreamInterface $stream, string $encoding): array
    {
        $gedcom_records = [];

        // Read the file in blocks of roughly 64K. Ensure that each block
        // contains complete gedcom records. This will ensure we don’t split
        // multi-byte characters, as well as simplifying the code to import
        // each block.

        $file_data = '';
        $stream = $stream->detach();

        // Convert to UTF-8.
        stream_filter_append($stream, GedcomEncodingFilter::class, STREAM_FILTER_READ, ['src_encoding' => $encoding]);

        while (!feof($stream)) {
            $file_data .= fread($stream, 65536);
            $eol_pos = max((int) strrpos($file_data, "\r0"), (int) strrpos($file_data, "\n0"));

            if ($eol_pos > 0) {
                $chunk_data = substr($file_data, 0, $eol_pos + 1);
                $chunk_data = str_replace("\r\n", "\n", $chunk_data);

                $remaining_string = $this->addToGedcomRecords($gedcom_records, $chunk_data);

                $file_data = $remaining_string . substr($file_data, $eol_pos + 1);
            }
        }

        $chunk_data = $file_data;
        $chunk_data = str_replace("\r\n", "\n", $chunk_data);
        $remaining_string = $this->addToGedcomRecords($gedcom_records, $chunk_data);
        $gedcom_records[] = $remaining_string;

        fclose($stream);

        return $gedcom_records;
    }

    /**
     * Add data from a chunk of Gedcom data to a set of Gedcom records
     *
     * @param array<string>  $gedcom_records   A set of Gedcom records
     * @param string         $chunk_data       A chunk of Gedcom data
     *
     * @return string                          The remaining end of the chunk 
     */
    public function addToGedcomRecords(array &$gedcom_records, string &$chunk_data): string
    {
        // Split the Gedcom strucuture into sub structures 
        // See: Fisharebest\Webtrees\GedcomRecord, function parseFacts()
        $parsed_gedcom_structures = preg_split('/\n(?=0)/', $chunk_data);
        $size = sizeof($parsed_gedcom_structures);
        $last_string = $parsed_gedcom_structures[$size-1];
        unset($parsed_gedcom_structures[$size-1]);

        //Add to the set of Gedcom records
        $gedcom_records = array_merge($gedcom_records, $parsed_gedcom_structures);

        return $last_string;
    }

	/**
     * Execute the request (from URL or from control panel) to download or save 
     * 
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        //Check update of module version
        $this->checkModuleVersionUpdate();

        $called_from_control_panel = false;
        $encodings          = ['' => ''] + Registry::encodingFactory()->list();

		$action              = Validator::queryParams($request)->string('action', self::ACTION_DOWNLOAD);
		$key                 = Validator::queryParams($request)->string('key', '');
		$tree_name           = Validator::queryParams($request)->string('tree', '');
        $filename            = Validator::queryParams($request)->string('file', $tree_name);
        $format              = Validator::queryParams($request)->string('format',  $this->getPreference(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom'));
        $privacy             = Validator::queryParams($request)->string('privacy',  $this->getPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, 'visitor'));
        $encoding            = Validator::queryParams($request)->string('encoding',  $this->getPreference(self::PREF_DEFAULT_ENCODING, UTF8::NAME));
        $line_endings        = Validator::queryParams($request)->string('line_endings',  $this->getPreference(self::PREF_DEFAULT_ENDING, 'CRLF'));
		$time_stamp          = Validator::queryParams($request)->string('time_stamp', $this->getPreference(self::PREF_DEFAULT_TIME_STAMP, self::TIME_STAMP_NONE));
		$gedcom_filter1      = Validator::queryParams($request)->string('gedcom_filter1', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER1, ''));
		$gedcom_filter2      = Validator::queryParams($request)->string('gedcom_filter2', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER2, ''));
		$gedcom_filter3      = Validator::queryParams($request)->string('gedcom_filter3', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER3, ''));
        $source              = 'server';
        $import_encoding     = Validator::queryParams($request)->isInArrayKeys($encodings)->string('import_encoding', '');
        $keep_media          = Validator::queryParams($request)->boolean('keep_media', false);
        $word_wrapped_notes  = Validator::queryParams($request)->boolean('word_wrapped_notes', false);
        $gedcom_media_path   = Validator::queryParams($request)->string('gedcom_media_path', '');

        // If POST requests (from control panel), parse certain parameters accordingly
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {

            $called_from_control_panel = Validator::parsedBody($request)->boolean('called_from_control_panel', false);
            $tree_name                 = Validator::parsedBody($request)->string('tree', '');
            $filename                  = Validator::parsedBody($request)->string('filename', $tree_name);
            $format                    = Validator::parsedBody($request)->string('format', 'gedcom');
            $privacy                   = Validator::parsedBody($request)->string('privacy', 'visitor');
            $encoding                  = Validator::parsedBody($request)->string('encoding', UTF8::NAME);
            $line_endings              = Validator::parsedBody($request)->string('line_endings', 'CRLF');
            $time_stamp                = Validator::parsedBody($request)->string('time_stamp', self::TIME_STAMP_NONE);
            $gedcom_filter1            = Validator::parsedBody($request)->string('gedcom_filter1', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER1, ''));
            $gedcom_filter2            = Validator::parsedBody($request)->string('gedcom_filter2', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER2, ''));
            $gedcom_filter3            = Validator::parsedBody($request)->string('gedcom_filter3', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER3, ''));
            $source                    = Validator::parsedBody($request)->isInArray(['client', 'server'])->string('source', '');
            $import_encoding           = Validator::parsedBody($request)->isInArrayKeys($encodings)->string('import_encoding', '');
            $keep_media                = Validator::parsedBody($request)->boolean('keep_media', false);
            $word_wrapped_notes        = Validator::parsedBody($request)->boolean('word_wrapped_notes', false);
            $gedcom_media_path         = Validator::parsedBody($request)->string('gedcom_media_path', '');
        }        

        $tree_service  = new TreeService(new GedcomImportService);

        if ($tree_name !== '') {
            $tree  = $tree_service->all()[$tree_name];
        }
        else {
            $tree = null;
        }

        //Get folder from module settings and file system
        $folder_on_server = $this->getPreference(DownloadGedcomWithURL::PREF_FOLDER_TO_SAVE, '');

        //Add namespace to Gedcom filters
        $gedcom_filter_class_name1 = __NAMESPACE__ . '\\' . $gedcom_filter1;
        $gedcom_filter_class_name2 = __NAMESPACE__ . '\\' . $gedcom_filter2;
        $gedcom_filter_class_name3 = __NAMESPACE__ . '\\' . $gedcom_filter3;

        //Load Gedcom filter classes
        if ($gedcom_filter1 !== '' OR $gedcom_filter2 !== '' OR $gedcom_filter3 !== '') {
            try {
                self::loadGedcomFilterClasses();
            }
            catch (DownloadGedcomWithUrlException $ex) {
                return $this->showErrorMessage($ex->getMessage());
            }    
        }

        //If not called from control panel (i.e. called remotely via URL), evaluate key
        if (!$called_from_control_panel) {

            //Load secret key from preferences
            $secret_key = $this->getPreference(self::PREF_SECRET_KEY, '');

            //Error if key is empty
            if ($key === '') {
                return $this->showErrorMessage(I18N::translate('No key provided. For checking of the access rights, it is mandatory to provide a key as parameter in the URL.'));
            }
            //Error if secret key is empty
            elseif ($secret_key === '') {
                return $this->showErrorMessage(I18N::translate('No secret key defined. Please define secret key in the module settings: Control Panel / Modules / All Modules / ') . $this->title());
            }
            //Error if no hashing and key is not valid
            elseif (!boolval($this->getPreference(self::PREF_USE_HASH, '0')) && ($key !== $secret_key)) {
                return $this->showErrorMessage(I18N::translate('Key not accepted. Access denied.'));
            }
            //Error if hashing and key does not fit to hash
            elseif (boolval($this->getPreference(self::PREF_USE_HASH, '0')) && (!password_verify($key, $secret_key))) {
                return $this->showErrorMessage(I18N::translate('Key (encrypted) not accepted. Access denied.'));
            }     
        }

        //Error if tree name is not valid
        if ($action !== self::ACTION_CONVERT && !$this->isValidTree($tree_name)) {
			return $this->showErrorMessage(I18N::translate('Tree not found') . ': ' . $tree_name);
		}
        //Error if privacy level is not valid
		elseif (!in_array($privacy, ['none', 'gedadmin', 'user', 'visitor'])) {
			return $this->showErrorMessage(I18N::translate('Privacy level not accepted') . ': ' . $privacy);
        }
        //Error if export format is not valid
        elseif (!in_array($format, ['gedcom', 'zip', 'zipmedia', 'gedzip'])) {
			return $this->showErrorMessage(I18N::translate('Export format not accepted') . ': ' . $format);
        }       
        //Error if encoding is not valid
		elseif (!in_array($encoding, ['', UTF8::NAME, UTF16BE::NAME, ANSEL::NAME, ASCII::NAME, Windows1252::NAME])) {
			return $this->showErrorMessage(I18N::translate('Encoding not accepted') . ': ' . $encoding);
        }       
        //Error action is not valid
        elseif (!in_array($action, [self::ACTION_DOWNLOAD, self::ACTION_SAVE, self::ACTION_BOTH, self::ACTION_UPLOAD, self::ACTION_CONVERT])) {
			return $this->showErrorMessage(I18N::translate('Action not accepted') . ': ' . $action);
        }  
		//Error if line ending is not valid
        elseif (!in_array($line_endings, ['CRLF', 'LF'])) {
			return $this->showErrorMessage(I18N::translate('Line endings not accepted') . ': ' . $line_endings);
        } 
		//Error if time_stamp is not valid
        elseif (!in_array($time_stamp, [self::TIME_STAMP_PREFIX, self::TIME_STAMP_POSTFIX, self::TIME_STAMP_NONE])) {
			return $this->showErrorMessage(I18N::translate('Time stamp setting not accepted') . ': ' . $time_stamp);
        } 	
		//Error if Gedcom filter 1 is not found
        elseif ($gedcom_filter1 !== '' && (!class_exists($gedcom_filter_class_name1) OR !(new $gedcom_filter_class_name1() instanceof GedcomFilterInterface))) {
            return $this->showErrorMessage(I18N::translate('The GEDCOM filter was not found') . ': ' . $gedcom_filter1);
        }
		//Error if Gedcom filter 2 is not found
        elseif ($gedcom_filter2 !== '' && (!class_exists($gedcom_filter_class_name2) OR !(new $gedcom_filter_class_name2() instanceof GedcomFilterInterface))) {
            return $this->showErrorMessage(I18N::translate('The GEDCOM filter was not found') . ': ' . $gedcom_filter2);
        }
		//Error if Gedcom filter 3 is not found
        elseif ($gedcom_filter3 !== '' && (!class_exists($gedcom_filter_class_name3) OR !(new $gedcom_filter_class_name3() instanceof GedcomFilterInterface))) {
            return $this->showErrorMessage(I18N::translate('The GEDCOM filter was not found') . ': ' . $gedcom_filter3);
        }
		//Error if Gedcom filter 1 validation fails
        elseif ($gedcom_filter1 !== '' && ($error = $this->validateGedcomFilter($gedcom_filter1)) !== '') {
            return $this->showErrorMessage($error);
        }
		//Error if Gedcom filter 2 validation fails
        elseif ($gedcom_filter2 !== '' && ($error = $this->validateGedcomFilter($gedcom_filter2)) !== '') {
            return $this->showErrorMessage($error);
        }
		//Error if Gedcom filter 3 validation fails
        elseif ($gedcom_filter3 !== '' && ($error = $this->validateGedcomFilter($gedcom_filter3)) !== '') {
            return $this->showErrorMessage($error);
        }

		//If no errors, execute the core activities of the module

        //Get the tree
        if ($action !== self::ACTION_CONVERT) {
            try {
                $tree = $this->tree_service->all()[$tree_name];
                assert($tree instanceof Tree);
            }
            catch (Throwable $ex) {
                $message = I18N::translate('Could not find the requested tree "%s".', $tree_name);
                return $this->showErrorMessage($message);
            }             
        }

        //Initialize filters and get filter list
        try {
            $gedcom_filter_set = $this->createGedcomFilterList($gedcom_filter1, $gedcom_filter2, $gedcom_filter3);
        }
        catch (DownloadGedcomWithUrlException $ex) {
            return $this->showErrorMessage($ex->getMessage());
        }

        //Add time stamp to file name if requested
        if($time_stamp === self::TIME_STAMP_PREFIX){
            $filename = date('Y-m-d_H-i-s_') . $filename;
        } 
        elseif($time_stamp === self::TIME_STAMP_POSTFIX){
            $filename .= date('_Y-m-d_H-i-s');
        }

        //If saving to server is requested and allowed
        if (($action === self::ACTION_SAVE) OR ($action === self::ACTION_BOTH)) {

            if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_SAVE, '0'))) {

                // File extension
                if ($format === 'gedcom') {
                    $extension = '.ged';
                } 
                elseif ($format === 'gedzip') {
                    $extension = '.gdz';
                }
                else {
                    $extension = '.zip';
                }

                $export_file_name = $filename . $extension;
 
                //Get folder from settings
                $folder_on_server = $this->getPreference(self::PREF_FOLDER_TO_SAVE, '');

                //Create response
                try {
                    $resource = $this->filtered_gedcom_export_service->filteredResource($tree, true, $encoding, $privacy, $line_endings, $export_file_name, $format, $gedcom_filter_set);
                    $this->root_filesystem->writeStream($folder_on_server . $export_file_name, $resource);
                    $response = $this->showSuccessMessage(I18N::translate('The family tree "%s" was exported to: %s', $tree_name, $folder_on_server . $export_file_name));
                } 
                catch (FilesystemException | UnableToWriteFile | DownloadGedcomWithUrlException $ex) {

                    if ($ex instanceof DownloadGedcomWithUrlException) {
                        return $this->showErrorMessage($ex->getMessage());
                    }
                    else {
                        return $this->showErrorMessage(I18N::translate('The file %s could not be created.', $folder_on_server . $export_file_name));
                    }
                }
            }
            else {
                return $this->showErrorMessage( I18N::translate('Remote URL requests to save GEDCOM files to the server are not allowed.') . ' ' . 
                                                I18N::translate('Please check the module settings in the control panel.'));
            }
        }

        //If download is requested and allowed
        if ($action === self::ACTION_DOWNLOAD OR $action === self::ACTION_BOTH) {
            if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, '0'))) {
                try {
                    //Create response
                    $response = $this->filtered_gedcom_export_service->filteredDownloadResponse($tree, true, $encoding, $privacy, $line_endings, $filename, $format, $gedcom_filter_set);
                }
                catch (DownloadGedcomWithUrlException $ex) {
                    return $this->showErrorMessage($ex->getMessage());
                }
            }
            else {
                return $this->showErrorMessage( I18N::translate('Remote URL requests to download GEDCOM files from the server are not allowed.') . ' ' . 
                                                I18N::translate('Please check the module settings in the control panel.'));
            }
        }

        //If upload or convert is requested
        if ($action === self::ACTION_UPLOAD OR $action === self::ACTION_CONVERT) {

            // Save choices as defaults
            if ($action === self::ACTION_UPLOAD) {
                $tree  = $tree_service->all()[$tree_name];
                $tree->setPreference('keep_media', $keep_media ? '1' : '0');
                $tree->setPreference('WORD_WRAPPED_NOTES', $word_wrapped_notes ? '1' : '0');
                $tree->setPreference('GEDCOM_MEDIA_PATH', $gedcom_media_path);     
            }

            if ($source === 'server') {

                //If server file has no extension, add .ged
                if (strpos($filename, '.ged', -4) === false && strpos($filename, '.zip', -4) === false && strpos($filename, '.gdz', -4) === false) {
                    $filename .= '.ged';
                }

                try {
                    $resource = $this->root_filesystem->readStream($folder_on_server . $filename);
                    $stream = $this->stream_factory->createStreamFromResource($resource);
                }
                catch (Throwable $ex) {
                    $message = I18N::translate('Unable to read file "%s".', $folder_on_server . $filename);
                    return $this->showErrorMessage($message);
                }
            }
            elseif ($source === 'client') {
                $client_file = $request->getUploadedFiles()['client_file'] ?? null;

                if ($client_file === null || $client_file->getError() === UPLOAD_ERR_NO_FILE) {
                    $message = MoreI18N::xlate('No GEDCOM file was received.');    
                    return $this->showErrorMessage($message);
                }
    
                if ($client_file->getError() !== UPLOAD_ERR_OK) {
                    throw new FileUploadException($client_file);
                }
    
                try {
                    $stream = $client_file->getStream(); 
                    $filename = basename($client_file->getClientFilename());                    
                }
                catch (Throwable $ex) {
                    $message = I18N::translate('Unable to read file "%s".', $client_file);
                    return $this->showErrorMessage($message);
                }   
            }
            else {
                $message = MoreI18N::xlate('No GEDCOM file was received.');
                return $this->showErrorMessage($message);
            }

            //Import the file to a set of Gedcom records
            try {
                $gedcom_records = $this->importGedcomFile($stream, $import_encoding);

                if ($action === self::ACTION_UPLOAD) {
                    $message = I18N::translate('The file "%s" was sucessfully uploaded for the family tree "%s"', $filename, $tree_name);
                    FlashMessages::addMessage($message, 'success');
                }
            }
            catch (Throwable $ex) {
                return $this->showErrorMessage($ex->getMessage());
            }            

            //Export file name and extension
            $extension = pathinfo($filename);
            $filename  = basename($filename,'.'.$extension['extension']);

            if ($format === 'gedcom') {
                $extension = '.ged';
            } 
            elseif ($format === 'gedzip') {
                $extension = '.gdz';
            }
            else {
                $extension = '.zip';
            }

            //Apply Gedcom filters
            $matched_tag_combinations = [];
            $gedcom_records = $this->filtered_gedcom_export_service->applyGedcomFilters($gedcom_records, $gedcom_filter_set, $matched_tag_combinations, $tree);
            
            if ($action === self::ACTION_CONVERT) {
                if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_CONVERT, '0'))) {
                    if ($source === 'client') {
                        //Create a response from the filtered data
                        return $this->filtered_gedcom_export_service->filteredDownloadResponse(
                            $tree, true, $encoding, $privacy, $line_endings, $filename, $format, [], new Collection($gedcom_records));
                    }
                    else {
                        //Download to the server
                
                        //Create response
                        $export_file_name = $folder_on_server . $filename . '_converted' . $extension;
                        try {
                            //Create a stream from the filtered data
                            $resource = $this->filtered_gedcom_export_service->filteredResource(
                                $tree, true, $encoding, $privacy, $line_endings, $filename, $format, [], new Collection($gedcom_records));

                            $this->root_filesystem->writeStream($export_file_name, $resource);
                            $response = $this->showSuccessMessage(I18N::translate('The GEDCOM file "%s" was converted to: %s', $filename, $export_file_name));
                        } 
                        catch (FilesystemException | UnableToWriteFile | DownloadGedcomWithUrlException $ex) {
        
                            if ($ex instanceof DownloadGedcomWithUrlException) {
                                return $this->showErrorMessage($ex->getMessage());
                            }
                            else {
                                return $this->showErrorMessage(I18N::translate('The file %s could not be created.', $folder_on_server . $export_file_name));
                            }
                        }
        
                    }
                }
                else {
                    return $this->showErrorMessage( I18N::translate('Remote URL requests to convert GEDCOM files on the server are not allowed.') . ' ' . 
                                                    I18N::translate('Please check the module settings in the control panel.'));
                }
            }

            if ($action === self::ACTION_UPLOAD) {
                if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_UPLOAD, '0'))) {

                    //Create a stream from the filtered data
                    $resource = $this->filtered_gedcom_export_service->filteredResource(
                        $tree, true, $encoding, $privacy, $line_endings, $filename, $format, [], new Collection($gedcom_records));
                    $stream = $this->stream_factory->createStreamFromResource($resource);

                    //Import the stream into the database
                    try {
                        $this->tree_service->importGedcomFile($tree, $stream, $filename, $encoding);
                    }
                    catch (Throwable $ex) {
                        return $this->showErrorMessage($ex->getMessage());
                    }      

                    //Redirect in order to process the Gedcom data of the imported file
                    return redirect(route(ManageTrees::class, ['tree' => $tree->name()]));
                }
                else {
                    return $this->showErrorMessage( I18N::translate('Remote URL requests to upload GEDCOM files to the server are not allowed.') . ' ' . 
                                                    I18N::translate('Please check the module settings in the control panel.'));
                }
            }
        }

        return $response;
    }
}
