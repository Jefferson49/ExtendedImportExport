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

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Cli\Console;
use Fisharebest\Webtrees\Encodings\ANSEL;
use Fisharebest\Webtrees\Encodings\ASCII;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\Windows1252;
use Fisharebest\Webtrees\Exceptions\FileUploadException;
use Fisharebest\Webtrees\Factories\GedcomRecordFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomFilters\GedcomEncodingFilter;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateTreeAction;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeTreesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\RenumberTreeAction;
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
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\TimeoutService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;
use Jefferson49\Webtrees\Exceptions\GithubCommunicationError;
use Jefferson49\Webtrees\Helpers\GithubService;
use Jefferson49\Webtrees\Internationalization\MoreI18N;
use Jefferson49\Webtrees\Helpers\Functions;
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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

use ErrorException;
use CURLFile;
use RuntimeException;
use ReflectionClass;
use stdClass;
use Throwable;

use function substr;
use function str_replace;


class DownloadGedcomWithURL extends AbstractModule implements 
	ModuleCustomInterface, 
	ModuleConfigInterface,
	RequestHandlerInterface,
    ModuleDataFixInterface,
    ModuleGlobalInterface,
    ModuleListInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleDataFixTrait;
    use ModuleGlobalTrait;
    use ModuleListTrait;

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

    //A set of standard parameters to be used for calling Gedcom filter
    private array $standard_params;

    //Path for temporary GEDCOM files
    private string $gedcom_temp_path;


	//Custom module version
	public const CUSTOM_VERSION = '4.2.10';

	//Routes
	protected const ROUTE_REMOTE_ACTION_OLD = '/DownloadGedcomWithURL';
	public const    ROUTE_REMOTE_ACTION     = '/ExtendedImportExport';
	protected const ROUTE_EXPORT_PAGE       = '/ExtendedGedcomExport';
	protected const ROUTE_IMPORT_PAGE       = '/ExtendedGedcomImport';
	protected const ROUTE_CONVERT_PAGE      = '/ExtendedGedcomConvert';
	protected const ROUTE_SELECTION_PAGE    = '/ExtendedImportExportSelection';

	//Github repository
	public const GITHUB_REPO = 'Jefferson49/ExtendedImportExport';

	//Github API URL to get the information about the latest releases
	public const GITHUB_API_LATEST_VERSION = 'https://api.github.com/repos/'. self::GITHUB_REPO . '/releases/latest';
	public const GITHUB_API_TAG_NAME_PREFIX = '"tag_name":"v';

	//Author of custom module
	public const CUSTOM_AUTHOR = 'Markus Hemprich';

    //Old module name (based on the installation folder)
	public const OLD_MODULE_NAME_FOR_PREFERENCES = '_download_gedcom_with_url_';

    //Strings cooresponding to variable names
    public const VAR_GEDOCM_FILTER = 'gedcom_filter';
    public const VAR_GEDCOM_FILTER_LIST = 'gedcom_filter_list';
    public const VAR_DATA_FIX_TYPES = 'types';
    public const VAR_DATA_FIX_DEFAULT_TYPE = 'default_type';

    //Prefences, Settings
	public const PREF_MODULE_VERSION = 'module_version';
    public const PREF_DEFAULT_TREE_NAME = 'default_tree_name';
	public const PREF_SECRET_KEY = "secret_key";
	public const PREF_USE_HASH = "use_hash";
	public const PREF_ALLOW_REMOTE_DOWNLOAD = "allow_remote_download";
	public const PREF_ALLOW_REMOTE_UPLOAD = "allow_remote_upload";
	public const PREF_ALLOW_REMOTE_SAVE = "allow_remote_save";
	public const PREF_ALLOW_REMOTE_CONVERT = "allow_remote_convert";
    public const PREF_ALLOW_REMOTE_GEDBAS_UPLOAD = 'allow_remote_gedbas_upload';
	public const PREF_SHOW_MENU_LIST_ITEM = "show_menu_list_item";
    public const PREF_ALLOW_GEDBAS_UPLOAD = 'allow_gedbas_upload';
    public const PREF_USE_HEAD_NOTE_FOR_GEDBAS = 'use_head_note_for_gedbas';
	public const PREF_FOLDER_TO_SAVE = "folder_to_save";
    public const PREF_DEFAULT_GEDCOM_FILTER1 = 'default_gedcom_filter1';
    public const PREF_DEFAULT_GEDCOM_FILTER2 = 'default_gedcom_filter2';
    public const PREF_DEFAULT_GEDCOM_FILTER3 = 'default_gedcom_filter3';
    public const PREF_DEFAULT_PRIVACY_LEVEL = 'default_privacy_level'; 
    public const PREF_DEFAULT_EXPORT_FORMAT = 'default_export_format';
    public const PREF_DEFAULT_ENCODING = 'default_encoding';
    public const PREF_DEFAULT_ENDING = 'default_ending';
    public const PREF_DEFAULT_TIME_STAMP = 'default_time_stamp';
    
    //Preferences for trees
    public const TREE_PREF_GEDBAS_ID = 'GEDBAS_Id';
    public const TREE_PREF_GEDBAS_TITLE = 'GEDBAS_title';
    public const TREE_PREF_GEDBAS_APIKEY = 'GEDBAS_apiKey';
    public const TREE_PREF_GEDBAS_DESCRIPTION = 'GEDBAS_description';

    //Actions
    public const ACTION_DOWNLOAD      = 'download';
    public const ACTION_SAVE          = 'save';
    public const ACTION_BOTH          = 'both';
    public const ACTION_GEDBAS        = 'GEDBAS';
    public const ACTION_UPLOAD        = 'upload';
    public const ACTION_CONVERT       = 'convert';
    public const ACTION_RENUMBER_XREF = 'renumber_tree';
    public const ACTION_MERGE_TREES   = 'merge_trees';
    public const ACTION_CREATE_TREE   = 'create_tree';
    public const CALLED_FROM_CONTROL_PANEL = "called_from_control_panel";

    //Time stamp values
    public const TIME_STAMP_PREFIX  = 'prefix';
    public const TIME_STAMP_POSTFIX = 'postfix';
    public const TIME_STAMP_NONE    = 'none';

    //Session values
    public const SESSION_GEDCOM_FILTERS = 'gedcom_filters';
    public const SESSION_RECORD_TYPE = 'record_type';
    public const SESSION_RECORDS_TO_FIX = 'records_to_fix';

	//Alert tpyes
	public const ALERT_DANGER = 'alert_danger';
	public const ALERT_SUCCESS = 'alert_success';

    //Maximum level of includes for Gedcom filters
    private const MAXIMUM_FILTER_INCLUDE_LEVELS = 10;

    //All GEDCOM records (for record selection in datafix)
    private const ALL_RECORDS = 'ALL';
    private const HEAD = 'HEAD';

    //Others
    private const UPLOAD_TEMP_FOLDER = 'tmp/';


   /**
     * DownloadGedcomWithURL constructor.
     */
    public function __construct()
    {
        //Caution: Do not use the shared library jefferson47/webtrees-common within __construct(), 
        //         because it might result in wrong autoload behavior        
    }

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        //Check update of module version
        $this->checkModuleVersionUpdate();

        //Initialize services etc.
        $response_factory = Functions::getFromContainer(ResponseFactoryInterface::class);
        $this->stream_factory = new Psr17Factory();
        $this->data_fix_service = New DataFixService();
        $this->tree_service   = new TreeService(new GedcomImportService);
        $this->filtered_gedcom_export_service = new FilteredGedcomExportService($response_factory, $this->stream_factory);

        //Initialize variables
        $this->matched_pattern_for_tag_combination_in_data_fix = [];
        $this->gedcom_filters_in_data_fix = [];
        $this->gedcom_filters_loaded_in_data_fix = false;
        $this->root_filesystem = Registry::filesystem()->root();
        $this->standard_params = [];
        $this->gedcom_temp_path = 'modules_v4/' . basename(__DIR__) . '/resources/temp/';

        $router = Registry::routeFactory()->routeMap();            

        //Register a route for remote requests
        $router
            ->get(static::class, self::ROUTE_REMOTE_ACTION, $this)
            ->allows(RequestMethodInterface::METHOD_POST);            

        //Register the old route of the former DownloadGedcomWithURL module
        $router
            ->get('DownloadGedcomWithURL', self::ROUTE_REMOTE_ACTION_OLD, $this)
            ->allows(RequestMethodInterface::METHOD_POST);            
            
        //Register a route for the selection view
        $router
            ->get(SelectionPage::class, self::ROUTE_SELECTION_PAGE)
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
        return Registry::cache()->file()->remember(
            $this->name() . '-latest-version',
            function (): string {

                try {
                    //Get latest release from GitHub
                    return GithubService::getLatestReleaseTag(self::GITHUB_REPO);
                }
                catch (GithubCommunicationError $ex) {
                    // Can't connect to GitHub?
                    return $this->customModuleVersion();
                }
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
        $this->layout = 'layouts/administration';       

        $base_url      = Validator::attributes($request)->string('base_url');

        //Load Gedcom filters
        try {
            self::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }
        
        //Generate a tree list with all the trees, the user has access to; authorization is checked in tree service
        $tree_list = $this->tree_service->titles();

        //Check the Gedcom filters, which are defined in the prefernces
        $this->checkFilterPreferences(self::PREF_DEFAULT_GEDCOM_FILTER1);
        $this->checkFilterPreferences(self::PREF_DEFAULT_GEDCOM_FILTER2);
        $this->checkFilterPreferences(self::PREF_DEFAULT_GEDCOM_FILTER3);

        $data_folder = str_replace('\\', '/', Registry::filesystem()->dataName());
		$root_folder = str_replace('\\', '/', Registry::filesystem()->rootName());
		$data_folder_relative = str_replace($root_folder, '', $data_folder);

        return $this->viewResponse(
            $this->name() . '::settings',
            [
                'title'                               => $this->title(),
                'tree_list'                           => $tree_list,
                'base_url'                            => $base_url,
                self::VAR_GEDCOM_FILTER_LIST          => $this->getGedcomFilterList(),
				self::PREF_SECRET_KEY                 => $this->getPreference(self::PREF_SECRET_KEY, ''),
				self::PREF_USE_HASH                   => boolval($this->getPreference(self::PREF_USE_HASH, '1')),
				self::PREF_ALLOW_REMOTE_DOWNLOAD      => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, '0')),
				self::PREF_ALLOW_REMOTE_UPLOAD        => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_UPLOAD, '0')),
				self::PREF_ALLOW_REMOTE_SAVE          => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_SAVE, '0')),
				self::PREF_ALLOW_REMOTE_CONVERT       => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_CONVERT, '0')),
				self::PREF_ALLOW_REMOTE_GEDBAS_UPLOAD => boolval($this->getPreference(self::PREF_ALLOW_REMOTE_GEDBAS_UPLOAD, '0')),
				self::PREF_SHOW_MENU_LIST_ITEM        => boolval($this->getPreference(self::PREF_SHOW_MENU_LIST_ITEM, '1')),
				self::PREF_ALLOW_GEDBAS_UPLOAD        => boolval($this->getPreference(self::PREF_ALLOW_GEDBAS_UPLOAD, '0')),
				self::PREF_USE_HEAD_NOTE_FOR_GEDBAS   => boolval($this->getPreference(self::PREF_USE_HEAD_NOTE_FOR_GEDBAS, '0')),
				self::PREF_FOLDER_TO_SAVE             => $this->getPreference(self::PREF_FOLDER_TO_SAVE, $data_folder_relative),
                self::PREF_DEFAULT_GEDCOM_FILTER1     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER1, ''),
                self::PREF_DEFAULT_GEDCOM_FILTER2     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER2, ''),
                self::PREF_DEFAULT_GEDCOM_FILTER3     => $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER3, ''),
                self::PREF_DEFAULT_PRIVACY_LEVEL      => $this->getPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, 'none'),
                self::PREF_DEFAULT_EXPORT_FORMAT      => $this->getPreference(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom'),
                self::PREF_DEFAULT_ENCODING           => $this->getPreference(self::PREF_DEFAULT_ENCODING, UTF8::NAME),
                self::PREF_DEFAULT_ENDING             => $this->getPreference(self::PREF_DEFAULT_ENDING, 'CRLF'),
                self::PREF_DEFAULT_TIME_STAMP         => $this->getPreference(self::PREF_DEFAULT_TIME_STAMP, self::TIME_STAMP_NONE),
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
        $allow_remote_gedbas_upload = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_REMOTE_GEDBAS_UPLOAD, false);
        $new_secret_key             = Validator::parsedBody($request)->string('new_secret_key', '');
        $folder_to_save             = Validator::parsedBody($request)->string(self::PREF_FOLDER_TO_SAVE, Site::getPreference('INDEX_DIRECTORY'));
        $show_menu_list_item        = Validator::parsedBody($request)->boolean(self::PREF_SHOW_MENU_LIST_ITEM, false);
        $allow_gedbas_upload        = Validator::parsedBody($request)->boolean(self::PREF_ALLOW_GEDBAS_UPLOAD, false);
        $use_head_note_for_gedbas   = Validator::parsedBody($request)->boolean(self::PREF_USE_HEAD_NOTE_FOR_GEDBAS, false);
        $default_gedcom_filter1     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_FILTER1, '');
        $default_gedcom_filter2     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_FILTER2, '');
        $default_gedcom_filter3     = Validator::parsedBody($request)->string(self::PREF_DEFAULT_GEDCOM_FILTER3, '');
        $default_privacy_level      = Validator::parsedBody($request)->string(self::PREF_DEFAULT_PRIVACY_LEVEL, 'none');
        $default_export_format      = Validator::parsedBody($request)->string(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom');
        $default_encoding           = Validator::parsedBody($request)->string(self::PREF_DEFAULT_ENCODING, UTF8::NAME);
        $default_ending             = Validator::parsedBody($request)->string(self::PREF_DEFAULT_ENDING, 'CRLF');
        $default_time_stamp         = Validator::parsedBody($request)->string(self::PREF_DEFAULT_TIME_STAMP, self::TIME_STAMP_NONE);
        
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
				FlashMessages::addMessage(I18N::translate('The folder settings could not be saved, because the folder "%s" does not exist.', e($folder_to_save)), 'danger');
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
			$this->setPreference(self::PREF_ALLOW_REMOTE_GEDBAS_UPLOAD, $allow_remote_gedbas_upload ? '1' : '0');
			$this->setPreference(self::PREF_SHOW_MENU_LIST_ITEM, $show_menu_list_item ? '1' : '0');
			$this->setPreference(self::PREF_ALLOW_GEDBAS_UPLOAD, $allow_gedbas_upload ? '1' : '0');
			$this->setPreference(self::PREF_USE_HEAD_NOTE_FOR_GEDBAS, $use_head_note_for_gedbas ? '1' : '0');

            //Save default settings to preferences
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_FILTER1, $default_gedcom_filter1);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_FILTER2, $default_gedcom_filter2);
            $this->setPreference(self::PREF_DEFAULT_GEDCOM_FILTER3, $default_gedcom_filter3);
            $this->setPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, $default_privacy_level);
            $this->setPreference(self::PREF_DEFAULT_EXPORT_FORMAT, $default_export_format);
            $this->setPreference(self::PREF_DEFAULT_ENCODING, $default_encoding);
            $this->setPreference(self::PREF_DEFAULT_ENDING, $default_ending);
            $this->setPreference(self::PREF_DEFAULT_TIME_STAMP, $default_time_stamp);

            //Finally, show a success message
			$message = I18N::translate('The preferences for the module "%s" were updated.', $this->title());
			FlashMessages::addMessage($message, 'success');	
		}

        return redirect($this->getConfigLink());
    }

    /**
     * {@inheritDoc}
     *
     * @param Tree  $tree
     * @param array $parameters
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listUrl()
     */

    public function listUrl(Tree $tree, array $parameters = []): string
    {
        return route(SelectionPage::class, ['tree' => $tree->name()]);
    }    

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleGlobalInterface::headContent()
     */
    public function headContent(): string
    {
        //Include CSS file in head of webtrees HTML to make sure it is always found
        return '<link href="' . $this->assetUrl('css/extended-import-export.css') . '" type="text/css" rel="stylesheet" />';
    }

    /**
     * {@inheritDoc}
     *
     * @param Tree  $tree
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listIsEmpty()
     */    
    public function listIsEmpty(Tree $tree): bool
    {
        if (!Auth::isAdmin()  OR !boolval($this->getPreference(self::PREF_SHOW_MENU_LIST_ITEM, '1'))) {
            return true;
        }

        return false;
    }    

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listMenuClass()
     */
    public function listMenuClass(): string
    {
        //CSS class for module Icon (included in CSS file) is returned to be shown in the list menu
        return 'menu-list-extended-import-export';
    }

    /**
     * Check if module version is new and start update activities if needed
     *
     * @return void
     */
    public function checkModuleVersionUpdate(): void
    {
        $updated = false;

        //If started for the very first time, try to migrate preferences of former module
        if ($this->getPreference(self::PREF_MODULE_VERSION, '') === '') {
            $this->migratePreferencesFromFormerModule();
            $updated = true;
        }
       
        //Update custom module version if changed
        if($this->getPreference(self::PREF_MODULE_VERSION, '') !== self::CUSTOM_VERSION) {

            //Update module files
            if (require __DIR__ . '/update_module_files.php') {
                $this->setPreference(self::PREF_MODULE_VERSION, self::CUSTOM_VERSION);
                $updated = true;    
            }
        }

        if ($updated) {
            //Show flash message for update of preferences
            $message = I18N::translate('The preferences for the custom module "%s" were sucessfully updated to the new module version %s.', $this->title(), self::CUSTOM_VERSION);
            FlashMessages::addMessage($message, 'success');	
        }        
    }

    /**
     * Migration from former module DownloadGedcomWith URL to current module ExtendedImportExport
     *
     * @return void
     */
    public function migratePreferencesFromFormerModule(): void {

        $updated_settings = false;

        //If secret key is already stored and secret key hashing preference is not available (i.e. before module version v3.0.1)
        if(     Functions::getPreferenceForModule(self::OLD_MODULE_NAME_FOR_PREFERENCES, self::PREF_SECRET_KEY, '') !== '' 
            &&  Functions::getPreferenceForModule(self::OLD_MODULE_NAME_FOR_PREFERENCES, self::PREF_USE_HASH, '') === '') {

            //Set secret key hashing to false
            $this->setPreference(self::PREF_USE_HASH, '0');
            $updated_settings = true;
        }

        $preferences = [
            self::PREF_SECRET_KEY,
            self::PREF_USE_HASH,
            self::PREF_ALLOW_REMOTE_DOWNLOAD,
            self::PREF_ALLOW_REMOTE_UPLOAD,
            self::PREF_ALLOW_REMOTE_SAVE,
            self::PREF_ALLOW_REMOTE_CONVERT,
            self::PREF_SHOW_MENU_LIST_ITEM,
            self::PREF_FOLDER_TO_SAVE,
            self::PREF_DEFAULT_GEDCOM_FILTER1,
            self::PREF_DEFAULT_GEDCOM_FILTER2,
            self::PREF_DEFAULT_GEDCOM_FILTER3,
            self::PREF_DEFAULT_PRIVACY_LEVEL,
            self::PREF_DEFAULT_EXPORT_FORMAT,
            self::PREF_DEFAULT_ENCODING,
            self::PREF_DEFAULT_ENDING,
            self::PREF_DEFAULT_TIME_STAMP,
        ];   

        foreach($preferences as $preference) {
            $setting_value = Functions::getPreferenceForModule(self::OLD_MODULE_NAME_FOR_PREFERENCES, $preference, '');

            if ($setting_value !== '') {
                $this->setPreference($preference, $setting_value);
                $updated_settings = true;
            } 
        }

        if ($updated_settings) {
            //Show flash message for update of preferences
            $message = I18N::translate('The preferences for the custom module %s were imported from the earlier custom module version %s.', $this->title(), 'DownloadGedcomWithURL');
            FlashMessages::addMessage($message, 'success');	
        }  
    }

    /**
     * Check if a Gedcom filter is available. If not, reset Gedcom filter to none
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
     * Send a response, depending on the client type
     *
     * @param string $text
     * @param bool   $is_error     Whether the response contains an error
     * @param bool   $for_browser  Whether the client is a browser and we respond with a view; otherwise plain text is returned, e.g. for scripts
     * 
     * @return ResponseInterface
     */ 
    public function sendResponse(string $text, bool $is_error = false, bool $for_browser = true): ResponseInterface
	{		
        $title = $is_error ? MoreI18N::xlate('Error') : MoreI18N::xlate('Success');

        if ($for_browser) {

            //Return a view, i.e. for a browser
            return $this->viewResponse($this->name() . '::alert', [
                'title'        	=> $title,
                'tree'			=> null,
                'alert_type'    => $is_error ? DownloadGedcomWithURL::ALERT_DANGER : DownloadGedcomWithURL::ALERT_SUCCESS,
                'module_name'	=> $this->title(),
                'text'  	   	=> $text,
            ]);	 
        }

        //Return plain text, e.g. for a script
        return response($title . ': ' . $text, $is_error ? StatusCodeInterface::STATUS_OK : StatusCodeInterface::STATUS_OK);
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

        foreach (get_declared_classes() as $class_name) {

            $name_space = str_replace('\\\\', '\\',__NAMESPACE__ ) .'\\';

            if (strpos($class_name, $name_space) !==  false) {
                if (in_array($name_space . 'GedcomFilterInterface', class_implements($class_name))) {
                    if ($class_name !== $name_space . 'AbstractGedcomFilter') {
                        $filter = new $class_name();
                        $class_name = str_replace($name_space, '', $class_name);    
                        $gedcom_filter_list[$class_name] = $filter->name();
                    }
                }
            }
        }

        uasort($gedcom_filter_list, function (string $a, string $b) {
            return strcmp($a, $b);
        });

        $no_filter = ['' => I18N::translate('No filter')];

        return $no_filter + $gedcom_filter_list;
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

        if (!class_exists($gedcom_filter_class_name) OR !($this->getInstanceOfGedcomFilter($gedcom_filter_class_name) instanceof GedcomFilterInterface)) {

            return I18N::translate('The GEDCOM filter was not found') . ': ' . $gedcom_filter_name;
        }

        $gedcom_filter_instance = $this->getInstanceOfGedcomFilter($gedcom_filter_class_name);

        //Validate the content of the Gedcom filter
        $error = $gedcom_filter_instance !== null ? $gedcom_filter_instance->validate() : '';

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

        //Reset stored gedcom filters and records to fix
        Session::forget('gedcom_filters');
        Session::forget('records_to_fix');

        //Load Gedcom filters
        try {
            self::loadGedcomFilterClasses();
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }

        $types = [
            self::ALL_RECORDS       => MoreI18N::xlate('All'),
            self::HEAD              => MoreI18N::xlate('Header'),
            Family::RECORD_TYPE     => MoreI18N::xlate('Families'),
            Individual::RECORD_TYPE => MoreI18N::xlate('Individuals'),
            Location::RECORD_TYPE   => MoreI18N::xlate('Locations'),
            Media::RECORD_TYPE      => MoreI18N::xlate('Media objects'),
            Note::RECORD_TYPE       => MoreI18N::xlate('Shared notes'),
            Repository::RECORD_TYPE => MoreI18N::xlate('Repositories'),
            Source::RECORD_TYPE     => MoreI18N::xlate('Sources'),
            Submitter::RECORD_TYPE  => MoreI18N::xlate('Submitters'),
        ];        

        return view(
            self::viewsNamespace() . '::options',
            [
                'tree'                          => $tree,
                self::VAR_GEDCOM_FILTER_LIST    => $this->getGedcomFilterList(),
                self::VAR_GEDOCM_FILTER . '1'   => '',
                self::VAR_GEDOCM_FILTER . '2'   => '',
                self::VAR_GEDOCM_FILTER . '3'   => '',
                self::VAR_DATA_FIX_TYPES        => $types,
                self::VAR_DATA_FIX_DEFAULT_TYPE => self::ALL_RECORDS,
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
        $current_gedcom_filters = [
            $params[self::VAR_GEDOCM_FILTER . '1'], 
            $params[self::VAR_GEDOCM_FILTER . '2'], 
            $params[self::VAR_GEDOCM_FILTER . '3'], 
        ];

        $record_type = $params['type'];

        //If filters or types have changed, save to session and reset cached records to fix
        if ($current_gedcom_filters !== Session::get(self::SESSION_GEDCOM_FILTERS) OR $record_type  !== Session::get(self::SESSION_RECORD_TYPE)) {

            Session::put(self::SESSION_GEDCOM_FILTERS,  $current_gedcom_filters);
            Session::put(self::SESSION_RECORD_TYPE,  $record_type);
            Session::forget(self::SESSION_RECORDS_TO_FIX);
        }

        $cached_records_to_fix = Session::get(self::SESSION_RECORDS_TO_FIX);

        if ($cached_records_to_fix !== null) {
            return $cached_records_to_fix;
        }

        $families     = $this->familiesToFixQuery($tree, $params)->pluck('f_id');
        $individuals  = $this->individualsToFixQuery($tree, $params)->pluck('i_id');
        $locations    = $this->locationsToFixQuery($tree, $params)->pluck('o_id');
        $media        = $this->mediaToFixQuery($tree, $params)->pluck('m_id');
        $notes        = $this->notesToFixQuery($tree, $params)->pluck('o_id');
        $repositories = $this->repositoriesToFixQuery($tree, $params)->pluck('o_id');
        $sources      = $this->sourcesToFixQuery($tree, $params)->pluck('s_id');
        $submitters   = $this->submittersToFixQuery($tree, $params)->pluck('o_id');

        $records = new Collection();

        //Header
        $header = new stdClass;
        $header->xref = 'HEAD';
        $header->type = 'HEAD';

        if ($record_type === self::HEAD OR $record_type === self::ALL_RECORDS) {
            $records = $records->concat([$header]);
        }
        
        if ($families !== null && ($record_type === Family::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($families, $tree, Family::RECORD_TYPE));
        }

        if ($individuals !== null && ($record_type === Individual::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($individuals, $tree, Individual::RECORD_TYPE));
        }

        if ($locations !== null && ($record_type === Location::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($locations, $tree, Location::RECORD_TYPE));
        }

        if ($media !== null && ($record_type === Media::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($media, $tree, Media::RECORD_TYPE));
        }

        if ($notes !== null && ($record_type === Note::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($notes, $tree, Note::RECORD_TYPE));
        }

        if ($repositories !== null && ($record_type === Repository::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($repositories, $tree, Repository::RECORD_TYPE));
        }

        if ($sources !== null && ($record_type === Source::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($sources, $tree, Source::RECORD_TYPE));
        }

        if ($submitters !== null && ($record_type === Submitter::RECORD_TYPE OR $record_type === self::ALL_RECORDS)) {
            $records = $records->concat($this->mergePendingRecords($submitters, $tree, Submitter::RECORD_TYPE));
        }

        //Add TRLR
        $trailer = new stdClass;
        $trailer->xref = 'TRLR';
        $trailer->type = 'TRLR';

        $records = $records->concat([$trailer]);

        //Sort records
        $records = $records
            ->unique()
            ->sort(static function (object $x, object $y) {
                return $x->xref <=> $y->xref;
            });

        $records_to_fix = new Collection();
        $gedcom_factory = new GedcomRecordFactory();

        //Get filters
        $this->gedcom_filters_in_data_fix = $this->getGedcomFiltersFromParams($params);

        if ($this->gedcom_filters_in_data_fix !== []) {

            //Set tree for filter configuration
            $this->standard_params['tree_name'] = $tree->name();

            //Identify records, which are modified by the used filters
            foreach ($records as $record) {

                $gedcom_record = $gedcom_factory->make($record->xref, $tree, null);
    
                if ($this->isRecordCModifiedByFilters($gedcom_record, $this->gedcom_filters_in_data_fix)) {

                    $records_to_fix->add($record);
                }
            }
        }

        //Save found records to session to prevent re-calculation in list views
        Session::put('records_to_fix', $records_to_fix);

        return $records_to_fix;
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
        //Get tree name in params
        $this->standard_params['tree_name'] = $params['tree_name'];

        //Get filters, if not yet available
        if ($this->gedcom_filters_in_data_fix === []) {
            $this->gedcom_filters_in_data_fix = $this->getGedcomFiltersFromParams($params);
        }

        if ($this->gedcom_filters_in_data_fix === []) return false;

        $gedcom = $record->gedcom();
        $filtered_records = $this->filtered_gedcom_export_service->applyGedcomFilters([$gedcom], $this->gedcom_filters_in_data_fix, $this->matched_pattern_for_tag_combination_in_data_fix, $this->standard_params);

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
        $filtered_records = $this->filtered_gedcom_export_service->applyGedcomFilters([$gedcom], $this->gedcom_filters_in_data_fix, $this->matched_pattern_for_tag_combination_in_data_fix, $this->standard_params);

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
        $filtered_records = $this->filtered_gedcom_export_service->applyGedcomFilters([$gedcom], $this->gedcom_filters_in_data_fix, $this->matched_pattern_for_tag_combination_in_data_fix, $this->standard_params);

        $new = $filtered_records[0] ?? $gedcom;  
        $record->updateRecord($new, false);

        return;
    }

    /**
     * Will a GEDCOM record be modified by a set of GEDCOM filters 
     *
     * @param GedcomRecord                 $record
     * @param array<GedcomFilterInterface> $gedcom_filters
     *
     * @return bool
     */
    public function isRecordCModifiedByFilters(GedcomRecord $record, array $gedcom_filters): bool
    {
        $gedcom = $record->gedcom();
        $filtered_records = $this->filtered_gedcom_export_service->applyGedcomFilters([$gedcom], $gedcom_filters, $this->matched_pattern_for_tag_combination_in_data_fix, $this->standard_params);

        $old = $gedcom . "\n";
        $new = $filtered_records[0] ?? $gedcom;

        return $new !== $old;
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

        foreach ($params as $key => $value) {

            if (strpos($key, self::VAR_GEDOCM_FILTER) !== false && $value !== '') {
                $gedcom_filters[] = $value;
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
            $gedcom_filters = $this->createGedcomFilterList([$gedcom_filter1, $gedcom_filter2, $gedcom_filter3]);
        }
        catch (DownloadGedcomWithUrlException $ex) {
            FlashMessages::addMessage($ex->getMessage(), 'danger');
        }
        
        return $gedcom_filters;
    }

	/**
     * Create a list of Gedcom filters from filter names; also handle include structure of the filters
     * 
     * @param array<string> $class_names      An array with Gedcom filter class names
     *
     * @return array<GedcomFilterInterface>   A set of Gedcom filters
     */	
    public function createGedcomFilterList(array $class_names): array    
    {
        $gedcom_filter_set = [];

        foreach ($class_names as $class_name) {
            $gedcom_filter_set[] = $this->getInstanceOfGedcomFilter($class_name);
        }

        //Add Gedcom filters, which might also add further Gedcom filters from their include lists
        $gedcom_filter_set = $this->addIncludedGedcomFilters([], $gedcom_filter_set, []);

        return $gedcom_filter_set;
    }

    /**
     * Get instance of GEDCOM filter
     * 
     * @param string $class_name       Class name of Gedcom filter (without namespace)
     *
     * @return GedcomFilterInterface   An instance of a  Gedcom filter
     */	
    private function getInstanceOfGedcomFilter(string $class_name): ?GedcomFilterInterface {

        if ($class_name === '') {
            return null;
        }

        //Add namespace to Gedcom filter
        $name_space = __NAMESPACE__;

        if (strpos($class_name, $name_space) === false) {
            $class_name = $name_space . '\\' . $class_name;
        }

        try {
            $gedcom_filter_instance = new $class_name();
        }
        catch (Throwable $th) {
            throw new DownloadGedcomWithUrlException(I18N::translate('The GEDCOM filter was not found') . ': ' . $class_name);
        }

        return $gedcom_filter_instance;
    }

    /**
     * Import data from a gedcom file to an array of Gedcom record strings.
     *
     * @param StreamInterface $stream             The GEDCOM file
     * @param string          $encoding           Override the encoding specified in the header
     * @param bool            $word_wrapped_notes Whether a space character shall be added to CONC structures during import
     *
     * @return array<string>                      A set of Gedcom record strings
     */
    public function importGedcomFileToRecords(StreamInterface $stream, string $encoding, bool $word_wrapped_notes = false): array
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

                $chunk_data = preg_replace("/\n([\d] " . Gedcom::REGEX_TAG . ")\n[\d] CONC /", "\n$1 ", $chunk_data);
                $chunk_data = preg_replace("/\n[\d] CONC /", $word_wrapped_notes ? ' ' : '', $chunk_data);

                $remaining_string = $this->addToGedcomRecords($gedcom_records, $chunk_data);

                $file_data = $remaining_string . substr($file_data, $eol_pos + 1);
            }
        }

        $chunk_data = $file_data;
        $chunk_data = str_replace("\r\n", "\n", $chunk_data);
        $remaining_string = $this->addToGedcomRecords($gedcom_records, $chunk_data);

        if ($remaining_string !== '') {
            $gedcom_records[] = $remaining_string;
        }

        fclose($stream);

        //Remove any byte-order-mark
        if (!empty($gedcom_records)) {

            $first_record = reset($gedcom_records);
            $first_key    = key($gedcom_records);

            if (str_starts_with($first_record, UTF8::BYTE_ORDER_MARK)) {
                $first_record = substr($first_record, strlen(UTF8::BYTE_ORDER_MARK));
                $gedcom_records[$first_key] = $first_record;
            }

            if (!str_starts_with($first_record, '0 HEAD')) {
                throw new DownloadGedcomWithUrlException(MoreI18N::xlate('Invalid GEDCOM file - no header record found.'));
            }
        }

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
     * Get params from request 
     * 
     * @param ServerRequestInterface $request
     *
     * @return array<string>
     */	
    public function getParamsFromRequest(ServerRequestInterface $request): array
    {    
        $params = [];
        $params['base_url'] = Validator::attributes($request)->string('base_url');

        // If GET request
        if ($request->getMethod() === RequestMethodInterface::METHOD_GET) {
            $params['tree']        = Validator::queryParams($request)->string('tree', '');
            $params['tree_name']   = Validator::queryParams($request)->string('tree', '');
            $this->standard_params = $params;

            $query_params = $request->getQueryParams();

            foreach ($query_params as $name => $value) {
                $params[$name] = Validator::queryParams($request)->string($name, '');
            }
        }     
        // If POST request (from control panel)
        elseif ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $params['tree']        = Validator::parsedBody($request)->string('tree', '');
            $params['tree_name']   = Validator::parsedBody($request)->string('tree', '');
            $this->standard_params = $params;
        }

        return $params;
    }

	/**
     * Evaluate filename and extension 
     * 
     * @param string $filename
     * @param string $action
     * @param string $format
     *
     * @return array<string>     ['filename' => file name, 'extension' => extension]
     */	
    private function evaluateFilename(string $filename, string $action, string $format): array
    {
        $path_info = pathinfo($filename);
        $extension = $path_info['extension'] ?? '';
        $extension = $extension !== '' ? '.' . $extension : '';
        $filename  = basename($filename, $extension);
    
        //For downloads, overrule extensions by format settings
        if (in_array($action, [self::ACTION_DOWNLOAD, self::ACTION_SAVE, self::ACTION_BOTH])) {
            if ($format === 'gedcom') {
                $extension = '.ged';
            } 
            elseif ($format === 'gedzip') {
                $extension = '.gdz';
            }
            elseif ($format === 'zip') {
                $extension = '.zip';
            }
            elseif ($format === 'zipmedia') {
                $extension = '.zip';
            }
        }
        //For reading files from the server, add .ged extension
        elseif (in_array($action, [self::ACTION_UPLOAD, self::ACTION_CONVERT])) {
            if ($extension === '') {
                $extension = '.ged';
            }
        }
        
        return ['filename' => $filename, 'extension' => $extension];
    }

	/**
     * Get the records stored in the clippings cart
     * Code from: ClippingsCartModule, function postDownloadAction
     *
     * @param  Tree       $tree
     * @param  string     $privacy
     * @return Collection
     */	
    private function getClippingsCartRecords(Tree $tree, string $privacy): Collection
    {    
        $cart = Session::get('cart');
        $cart = is_array($cart) ? $cart : [];

        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs); // PHP converts numeric keys to integers.

        $records = new Collection();

        switch ($privacy) {
            case 'gedadmin':
                $access_level = Auth::PRIV_NONE;
                break;
            case 'user':
                $access_level = Auth::PRIV_USER;
                break;
            case 'visitor':
                $access_level = Auth::PRIV_PRIVATE;
                break;
            case 'none':
            default:
                $access_level = Auth::PRIV_HIDE;
                break;
        }        

        foreach ($xrefs as $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            // The object may have been deleted since we added it to the cart....
            if ($object instanceof GedcomRecord && $object->canShow($access_level)) {
                $gedcom = $object->privatizeGedcom($access_level);

                // Remove links to objects that aren't in the cart
                $patterns = [
                    '/\n1 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(?:\n[2-9].*)*/',
                    '/\n2 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(?:\n[3-9].*)*/',
                    '/\n3 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(?:\n[4-9].*)*/',
                    '/\n4 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(?:\n[5-9].*)*/',
                ];

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $gedcom, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        if (!in_array($match[1], $xrefs, true)) {
                            // Remove the reference to any object that isn't in the cart
                            $gedcom = str_replace($match[0], '', $gedcom);
                        }
                    }
                }

                $records->add($gedcom);
            }
        }

        return $records;
    }

	/**
     * Upload GEDCOM file to GEDBAS
     *
     * @param  string  $GEDBAS_apiKey
     * @param  string  $GEDBAS_Id
     * @param  string  $file_name
     * @param  string  $file_location
     * @param  string  $title
     * @param  string  $description
     * @param  bool    $downloadAllowed
     * 
     * @return string
     * @throws GEDBASCommunicationException
     */	
    private function uploadToGEDBAS(
        string $GEDBAS_apiKey,
        string $GEDBAS_Id,
        string $file_name,
        string $file_location,
        string $title,
        string $description,
        bool   $downloadAllowed = false
    ): string 
    {
        if ($GEDBAS_apiKey === '') {
            throw new GEDBASCommunicationException(I18N::translate('Invalid GEDBAS API key'));
        }

        if ($GEDBAS_Id !== '' && filter_var($GEDBAS_Id, FILTER_VALIDATE_INT) === false) {
            throw new GEDBASCommunicationException(I18N::translate('GEDBAS Id does not contain an Integer: %s', $GEDBAS_Id));
        }

        if (strlen($file_name) < 5 OR strpos($file_name, '.ged', -4) === false) {
            throw new GEDBASCommunicationException(I18N::translate('Invalid file name for GEDBAS upload'));
        }

        // Configure cURL request
        $url = "https://gedbas.genealogy.net/database/saveWithApiKey";
        $cfile = new CURLFile($file_location,'text/plain',$file_name);
        $cdata = [
            "apiKey"          => $GEDBAS_apiKey,
            "file"            => $cfile,
            "title"           => $title,
            "description"     => $description,
            'downloadAllowed' => $downloadAllowed ? 'true' : 'false',
        ];

        //  Add GEDBAS id if already exists
        if ($GEDBAS_Id !== '') {
            $cdata['id'] = $GEDBAS_Id;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);

        // Execute cURL request to GEDBAS
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        //Throw exception if cURL error
        if ($curl_error !== '') {
            throw new GEDBASCommunicationException('curl_error: ' . $curl_error . ' response: ' . $response);
        }
        //Throw exception if GEDBAS Id does not contain an Integer, i.e. no valid GEDBAS database id
        elseif (filter_var($response, FILTER_VALIDATE_INT) === false) {
            throw new GEDBASCommunicationException($response);
        }

        return $response;
    }

	/**
     * Get information about existing databases from GEDBAS
     *
     * @param  string   $GEDBAS_apiKey
     * 
     * @return array
     * @throws GEDBASCommunicationException
     */	
    public function getDatabaseInfoFromGEDBAS(string $GEDBAS_apiKey): array 
    {
        if ($GEDBAS_apiKey === '') {
            throw new GEDBASCommunicationException(I18N::translate('Invalid GEDBAS API key'));
        }

        // Encode cURL request
        $url = "https://gedbas.genealogy.net/database/myFiles";

        // Use cURL to create the GEDBAS request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ["apiKey" => $GEDBAS_apiKey]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        //Throw exception if cURL error
        if ($curl_error !== '') {
            throw new GEDBASCommunicationException('curl_error: ' . $curl_error . ' response: ' . $response);
        }

        $database_info = json_decode($response, true);

        //Throw exception if invalid database information
        if (!is_array($database_info)) {
            throw new GEDBASCommunicationException('invalid database information: ' . $response);
        }

        return $database_info;
    }

	/**
     * Create a description of a tree for a GEDBAS upload
     *
     * @param  Tree $tree
     * 
     * @return string
     */	
    public function createGEDBASdescription(Tree $tree): string
    {
        //Retrieve HEAD:NOTE
        $header_note = '';
        If (boolval($this->getPreference(self::PREF_USE_HEAD_NOTE_FOR_GEDBAS, '0'))) {
            $header = $this->filtered_gedcom_export_service->createHeader($tree, UTF8::NAME, false);
            if (preg_match('/1 NOTE (.*)/', $header, $matches)) {
                $header_note = $matches[1];
            }
        }

        $description = $header_note !== '' ? $header_note : $tree->title(); 

        return $description;
    }

	/**
     * Import a tree into the database
     * 
     * @param Tree   $tree
     * @param string $gedcom_file
     * @param string $encoding
     * @param bool   $keep_media
     * @param bool   $conc_spaces
     * @param string $gedcom_media_path
     *
     * @return string
     * 
     * @throws RuntimeException
     */	
    private function importTree(Tree $tree, string $gedcom_file, string $encoding, bool $keep_media, bool $conc_spaces, string $gedcom_media_path): string
    {
        // Replace backslashes by slashes
        $gedcom_file = str_replace('\\' , '/', $gedcom_file);

        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        $console = new Console();
        $console->setAutoExit(false);
        $input = new ArrayInput([
                'command'             => 'tree-import',
                'tree-name'           => $tree->name(),
                'gedcom-file'         => $gedcom_file,
                '--encoding'          => $encoding,
                '--keep-media'        => $keep_media,
                '--conc-spaces'       => $conc_spaces,
                '--gedcom-media-path' => $gedcom_media_path,
            ]);
        $output  = new StreamOutput($stream);
        
        $exit_code = $console->loadCommands()->bootstrap()->run($input, $output);
        $error     = $exit_code !== 0;

        $output_stream = $this->stream_factory->createStreamFromResource($stream);
        $output_stream->rewind();
        $console_output = $output_stream->getContents();

        return $console_output;
    }

	/**
     * Execute the request (from URL or from control panel)
     * 
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */	
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $called_from_control_panel = false;
        $encodings = ['' => ''] + Registry::encodingFactory()->list();
        $tree = null;
        $word_wrapped_notes = false;
        $params = $this->getParamsFromRequest($request);

        // If GET request
        if ($request->getMethod() === RequestMethodInterface::METHOD_GET) {

            $tree_name                 = Validator::queryParams($request)->string('tree', '');
            $tree_to_merge_name        = Validator::queryParams($request)->string('tree_to_merge', '');
            $action                    = Validator::queryParams($request)->string('action', self::ACTION_DOWNLOAD);

            $all_trees = Functions::getAllTrees();

            if (!in_array($action, [self::ACTION_CONVERT, self::ACTION_CREATE_TREE])) {
                $tree = $all_trees->first(static function (Tree $tree) use ($tree_name): bool {
                    return $tree->name() === $tree_name;
                });
                if (!($tree instanceof Tree)) {
                    return $this->sendResponse(I18N::translate('Tree not found') . ': ' . $tree_name, true, $called_from_control_panel);
                }          
            }
            if ($action === self::ACTION_MERGE_TREES) {
                $tree_to_merge = $all_trees->first(static function (Tree $tree) use ($tree_to_merge_name): bool {
                    return $tree->name() === $tree_to_merge_name;
                });

                if (!($tree_to_merge instanceof Tree)) {
                    return $this->sendResponse(I18N::translate('Tree not found') . ': ' . $tree_to_merge_name, true, $called_from_control_panel);
                } else {
                    $tree_to_merge = $all_trees[$tree_to_merge_name];
                }            
            }

            $key                       = Validator::queryParams($request)->string('key', '');
            $filename                  = Validator::queryParams($request)->string('file', $tree_name);
            $filename_converted        = Validator::queryParams($request)->string('file_converted', '');
            $format                    = Validator::queryParams($request)->string('format',  $this->getPreference(self::PREF_DEFAULT_EXPORT_FORMAT, 'gedcom'));
            $privacy                   = Validator::queryParams($request)->string('privacy',  $this->getPreference(self::PREF_DEFAULT_PRIVACY_LEVEL, 'visitor'));
            $encoding                  = Validator::queryParams($request)->string('encoding',  $this->getPreference(self::PREF_DEFAULT_ENCODING, UTF8::NAME));
            $line_endings              = Validator::queryParams($request)->string('line_endings',  $this->getPreference(self::PREF_DEFAULT_ENDING, 'CRLF'));
            $time_stamp                = Validator::queryParams($request)->string('time_stamp', $this->getPreference(self::PREF_DEFAULT_TIME_STAMP, self::TIME_STAMP_NONE));
            $gedcom_filter1            = Validator::queryParams($request)->string('gedcom_filter1', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER1, ''));
            $gedcom_filter2            = Validator::queryParams($request)->string('gedcom_filter2', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER2, ''));
            $gedcom_filter3            = Validator::queryParams($request)->string('gedcom_filter3', $this->getPreference(self::PREF_DEFAULT_GEDCOM_FILTER3, ''));
            $source                    = 'server';
            $import_encoding           = Validator::queryParams($request)->isInArrayKeys($encodings)->string('import_encoding', '');
            $export_clippings_cart     = false;
            $GEDBAS_apiKey             = Validator::queryParams($request)->string('GEDBAS_apiKey', $tree !== null ? $tree->getPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_APIKEY, '') : '');
            $GEDBAS_Id                 = Validator::queryParams($request)->string('GEDBAS_Id', $tree !== null ? $tree->getPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_ID, '') : '');
            $GEDBAS_title              = Validator::queryParams($request)->string('GEDBAS_title', $tree !== null ? $tree->getPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_TITLE, '') : '');
            $GEDBAS_description        = Validator::queryParams($request)->string('GEDBAS_description', $tree !== null ? $this->createGEDBASdescription($tree) : '');

            if ($action === self::ACTION_UPLOAD) {
                $keep_media                = Validator::queryParams($request)->boolean('keep_media', boolval($tree->getPreference('keep_media', '0')));
                $word_wrapped_notes        = Validator::queryParams($request)->boolean('word_wrapped_notes', boolval($tree->getPreference('WORD_WRAPPED_NOTES', '0')));
                $gedcom_media_path         = Validator::queryParams($request)->string('gedcom_media_path', $tree->getPreference('GEDCOM_MEDIA_PATH', ''));
            }
        }

        // If POST request (from control panel), parse certain parameters accordingly
        elseif ($request->getMethod() === RequestMethodInterface::METHOD_POST) {

            $tree_name                 = Validator::parsedBody($request)->string('tree', '');
            $action                    = Validator::parsedBody($request)->string('action', self::ACTION_DOWNLOAD);

            if ($action !== self::ACTION_CONVERT) {
                $tree = $this->tree_service->all()[$tree_name];
            }

            $called_from_control_panel = Validator::parsedBody($request)->boolean('called_from_control_panel', false);
            $reload_form               = Validator::parsedBody($request)->boolean('reload_form', false);
            $export_clippings_cart     = Validator::parsedBody($request)->boolean('export_clippings_cart', false);
            $filename                  = Validator::parsedBody($request)->string('filename', $tree_name);
            $filename_converted        = Validator::parsedBody($request)->string('filename_converted', '');
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
            $GEDBAS_apiKey             = Validator::parsedBody($request)->string('GEDBAS_apiKey', '');
            $GEDBAS_Id                 = Validator::parsedBody($request)->string('GEDBAS_Id', '');
            $GEDBAS_title              = Validator::parsedBody($request)->string('GEDBAS_title', '');
            $GEDBAS_description        = Validator::parsedBody($request)->string('GEDBAS_description', '');

            if ($action === self::ACTION_UPLOAD) {
                $keep_media                = Validator::parsedBody($request)->boolean('keep_media', boolval($tree->getPreference('keep_media', '0')));
                $word_wrapped_notes        = Validator::parsedBody($request)->boolean('word_wrapped_notes', boolval($tree->getPreference('WORD_WRAPPED_NOTES', '0')));
                $gedcom_media_path         = Validator::parsedBody($request)->string('gedcom_media_path', $tree->getPreference('GEDCOM_MEDIA_PATH', ''));
            }

        }
        else {
            throw new DownloadGedcomWithUrlException(I18N::translate('Internal module error: Neither GET nor POST request received.'));
        }

        //Create the response parameters for the case we return to the control panel with the received options
        $parameters_for_control_panel = [
            'tree_name'             => $tree_name,
            'export_clippings_cart' => $export_clippings_cart,
            'filename'              => $filename,
            'action'                => $action,
            'format'                => $format,
            'privacy'               => $privacy,
            'encoding'              => $encoding,
            'endings'               => $line_endings,
            'time_stamp'            => $time_stamp,
            'GEDBAS_Id'             => $GEDBAS_Id,
            'GEDBAS_title'          => $GEDBAS_title,
            'GEDBAS_description'    => $GEDBAS_description,
            'gedcom_filter1'        => $gedcom_filter1,                        
            'gedcom_filter2'        => $gedcom_filter2,                        
            'gedcom_filter3'        => $gedcom_filter3,
        ];

        //If reload form for control panel is requested, i.e. new GEDBAS_apiKey was provided
        if ($called_from_control_panel && $reload_form) {

            //Check GEDBAS_apiKey
            try {
                $GEDBAS_database_info = $this->getDatabaseInfoFromGEDBAS($GEDBAS_apiKey);

                //Assign received GEDBAS apiKey to tree
                $tree->setPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_APIKEY, $GEDBAS_apiKey);
            } 
            catch (GEDBASCommunicationException $ex) {

                //Reset GEDBAS apiKey for tree
                $tree->setPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_APIKEY, '');

                $message = I18N::translate('Error during communication with GEDBAS'). ': ' . $ex->getMessage();
                FlashMessages::addMessage($message, 'danger');
            }

            return redirect(route(ExportGedcomPage::class, $parameters_for_control_panel));        
        }

        //Handle export of clippings cart
        if ($export_clippings_cart) {
            $clippings_cart_records = $this->getClippingsCartRecords($tree, $privacy);
            // If export of clippings cart, privacy rules are already handled in $this->getClippingsCartRecords; for further export, privacy is 'none' 
            $privacy = 'none';
        }
        else {
            $clippings_cart_records = null;
        }        

        //Get folder from module settings and file system
        $folder_on_server = $this->getPreference(DownloadGedcomWithURL::PREF_FOLDER_TO_SAVE, '');

        //If called from control panel, check if current user has the required access rights
        if ($called_from_control_panel) {

            if (in_array($action, [self::ACTION_DOWNLOAD, self::ACTION_SAVE, self::ACTION_BOTH, self::ACTION_GEDBAS])) {
                if (!Auth::isManager($tree)) { 
                    FlashMessages::addMessage(I18N::translate('Access denied. The user needs to be a manager of the tree.'), 'danger');	
                    return redirect(route(HomePage::class));
                }    
            }
            else {
                if (!Auth::isAdmin()) { 
                    FlashMessages::addMessage(I18N::translate('Access denied. The user needs to be an administrator.'), 'danger');
                    return redirect(route(HomePage::class));
                }    
            }
        }

        //If not called from control panel (i.e. called remotely via URL), evaluate key
        else {
            //Load secret key from preferences
            $secret_key = $this->getPreference(self::PREF_SECRET_KEY, '');

            //Error if key is empty
            if ($key === '') {
                return $this->sendResponse(I18N::translate('No key provided. For checking of the access rights, it is mandatory to provide a key as parameter in the URL.'), true, $called_from_control_panel);
            }
            //Error if secret key is empty
            if ($secret_key === '') {
                return $this->sendResponse(I18N::translate('No secret key defined. Please define secret key in the module settings: Control Panel / Modules / All Modules / ') . $this->title(), true, $called_from_control_panel);
            }
            //Error if no hashing and key is not valid
            if (!boolval($this->getPreference(self::PREF_USE_HASH, '0')) && ($key !== $secret_key)) {
                return $this->sendResponse(I18N::translate('Key not accepted. Access denied.'), true, $called_from_control_panel);
            }
            //Error if hashing and key does not fit to hash
            if (boolval($this->getPreference(self::PREF_USE_HASH, '0')) && (!password_verify($key, $secret_key))) {
                return $this->sendResponse(I18N::translate('Key (encrypted) not accepted. Access denied.'), true, $called_from_control_panel);
            }     
        }

        //Error if privacy level is not valid
		if (!in_array($privacy, ['none', 'gedadmin', 'user', 'visitor'])) {
			return $this->sendResponse(I18N::translate('Privacy level not accepted') . ': ' . $privacy, true, $called_from_control_panel);
        }
        //Error if export format is not valid
        if (!in_array($format, ['gedcom', 'zip', 'zipmedia', 'gedzip', 'other'])) {
			return $this->sendResponse(I18N::translate('Export format not accepted') . ': ' . $format, true, $called_from_control_panel);
        }       
        //Error if encoding is not valid
		if (!in_array($encoding, ['', UTF8::NAME, UTF16BE::NAME, ANSEL::NAME, ASCII::NAME, Windows1252::NAME])) {
			return $this->sendResponse(I18N::translate('Encoding not accepted') . ': ' . $encoding, true, $called_from_control_panel);
        }       
        //Error action is not valid
        if (!in_array($action, [self::ACTION_DOWNLOAD, self::ACTION_SAVE, self::ACTION_BOTH, self::ACTION_GEDBAS, self::ACTION_UPLOAD, self::ACTION_CONVERT, self::ACTION_RENUMBER_XREF, self::ACTION_MERGE_TREES, self::ACTION_CREATE_TREE])) {
			return $this->sendResponse(I18N::translate('Action not accepted') . ': ' . $action, true, $called_from_control_panel);
        }  
		//Error if line ending is not valid
        if (!in_array($line_endings, ['CRLF', 'LF'])) {
			return $this->sendResponse(I18N::translate('Line endings not accepted') . ': ' . $line_endings, true, $called_from_control_panel);
        } 
		//Error if time_stamp is not valid
        if (!in_array($time_stamp, [self::TIME_STAMP_PREFIX, self::TIME_STAMP_POSTFIX, self::TIME_STAMP_NONE])) {
			return $this->sendResponse(I18N::translate('Time stamp setting not accepted') . ': ' . $time_stamp, true, $called_from_control_panel);
        }
		//Error if conversion and no file name provided
        if (!$called_from_control_panel && $action === self::ACTION_CONVERT && $filename === '') {
			return $this->sendResponse(I18N::translate('No file name provided for the requested GEDCOM conversion'), true, $called_from_control_panel);
        }
		//Error if GEDBAS upload and wrong apiKey, Id, or filename
        if ($action === self::ACTION_GEDBAS) {

            $error_message = '';

            if ($GEDBAS_apiKey === '') {
                $error_message = I18N::translate('Error during GEDBAS upload.').  ' ' . I18N::translate('Empty GEDBAS API key.');
            }
            if ($GEDBAS_Id !== '' && filter_var($GEDBAS_Id, FILTER_VALIDATE_INT) === false) {
                $error_message = I18N::translate('Error during GEDBAS upload.'). ' ' . I18N::translate('GEDBAS Id does not contain an Integer: %s', $GEDBAS_Id);
            }
            if ($filename === '') {
                $error_message = I18N::translate('Error during GEDBAS upload.').  ' ' . I18N::translate('No filename provided.');
            }

            if ($error_message !== '') {
                if ($called_from_control_panel ) {
                    FlashMessages::addMessage($error_message, 'danger');
                    return redirect(route(ExportGedcomPage::class, $parameters_for_control_panel));                     
                }
                else {
                    return $this->sendResponse($error_message, true, $called_from_control_panel);
                }
            }
        }

        if ($action === self::ACTION_RENUMBER_XREF) {

            //Generate a request for the RenumberTreeAction
            $request         = Functions::getFromContainer(ServerRequestInterface::class);
            $request         = $request->withAttribute('tree', $tree instanceof Tree ? $tree : null);

            $request_handler = new RenumberTreeAction(new AdminService, new TimeoutService(new PhpService));
        
            return $request_handler->handle($request);
        }
        elseif ($action === self::ACTION_MERGE_TREES) {

            //Generate a request for the MergeTreesAction
            $request         = Functions::getFromContainer(ServerRequestInterface::class);
            $request         = $request->withParsedBody(['tree1_name' => $tree_to_merge->name(), 'tree2_name' => $tree->name()]);

            $request_handler = new MergeTreesAction(new AdminService, new TreeService(new GedcomImportService));
        
            return $request_handler->handle($request);
        }
        elseif ($action === self::ACTION_CREATE_TREE) {

            //Generate a request for the MergeTreesAction
            //Use tree name as title
            $request         = Functions::getFromContainer(ServerRequestInterface::class);
            $request         = $request->withParsedBody(['name' => $tree_name, 'title' => $tree_name]);

            $request_handler = new CreateTreeAction(new TreeService(new GedcomImportService));
        
            return $request_handler->handle($request);
        }
        
        if ($gedcom_filter1 !== '' OR $gedcom_filter2 !== '' OR $gedcom_filter3 !== '') {

            //Load Gedcom filter classes
            try {
                self::loadGedcomFilterClasses();
            }
            catch (DownloadGedcomWithUrlException $ex) {
                return $this->sendResponse($ex->getMessage(), true, $called_from_control_panel);
            }    

            //Error if Gedcom filter 1 validation fails
            if ($gedcom_filter1 !== '' && ($error = $this->validateGedcomFilter($gedcom_filter1)) !== '') {
                return $this->sendResponse($error, true, $called_from_control_panel);
            }
            //Error if Gedcom filter 2 validation fails
            if ($gedcom_filter2 !== '' && ($error = $this->validateGedcomFilter($gedcom_filter2)) !== '') {
                return $this->sendResponse($error, true, $called_from_control_panel);
            }
            //Error if Gedcom filter 3 validation fails
            if ($gedcom_filter3 !== '' && ($error = $this->validateGedcomFilter($gedcom_filter3)) !== '') {
                return $this->sendResponse($error, true, $called_from_control_panel);
            }

            //Initialize filters and get filter list
            try {
                $gedcom_filter_set = $this->createGedcomFilterList([$gedcom_filter1, $gedcom_filter2, $gedcom_filter3]);
            }
            catch (DownloadGedcomWithUrlException $ex) {
                return $this->sendResponse($ex->getMessage(), true, $called_from_control_panel);
            }
        }
        else {
            $gedcom_filter_set = [];
        }

        //Evaluate download filename
        $file_info = $this->evaluateFilename($filename, $action, $format);
        $filename = $file_info['filename'];
        $extension = $file_info['extension'];

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

                $export_file_name = $filename . $extension;

                try {
                    //Save file
                    $resource = $this->filtered_gedcom_export_service->filteredResource(
                    $tree, true, $encoding, $privacy, $line_endings, $filename, $format, $gedcom_filter_set, $params, $clippings_cart_records, $export_clippings_cart);
                    $this->root_filesystem->writeStream($folder_on_server . $export_file_name, $resource);
                } 
                catch (FilesystemException | UnableToWriteFile | DownloadGedcomWithUrlException $ex) {

                    if ($ex instanceof DownloadGedcomWithUrlException) {
                        return $this->sendResponse($ex->getMessage(), true, $called_from_control_panel);
                    }
                    else {
                        return $this->sendResponse(I18N::translate('The file %s could not be created.', $folder_on_server . $export_file_name), true, $called_from_control_panel);
                    }
                }

                $message = I18N::translate('The family tree "%s" was sucessfully exported to: %s', $tree_name, $folder_on_server . $export_file_name);

                if ($action === self::ACTION_SAVE) {

                    if ($called_from_control_panel) {
                        FlashMessages::addMessage($message, 'success');
                        return redirect(route(ExportGedcomPage::class, $parameters_for_control_panel));                     
                    }
                    else {
                        return $this->sendResponse($message, false, $called_from_control_panel);
                    }
                }
            }
            else {
                return $this->sendResponse( I18N::translate('Remote URL requests to save GEDCOM files to the server are not allowed.') . ' ' . 
                                            I18N::translate('Please check the module settings in the control panel.'),
                                            true, $called_from_control_panel);
            }
        }

        //If download is requested and allowed
        if ($action === self::ACTION_DOWNLOAD OR $action === self::ACTION_BOTH) {
            if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_DOWNLOAD, '0'))) {
                try {
                    //Return download response
                    return $this->filtered_gedcom_export_service->filteredDownloadResponse($tree, true, $encoding, $privacy, $line_endings, $filename, $extension, $format, $gedcom_filter_set, $params, $clippings_cart_records, $export_clippings_cart);
                }
                catch (DownloadGedcomWithUrlException $ex) {
                    return $this->sendResponse($ex->getMessage(), true, $called_from_control_panel);
                }
            }
            else {
                return $this->sendResponse(I18N::translate('Remote URL requests to download GEDCOM files from the server are not allowed.') . ' ' . 
                                        I18N::translate('Please check the module settings in the control panel.'),
                                        true, $called_from_control_panel);
            }
        }

        //If upload to GEDBAS is requested and allowed
        if (($action === self::ACTION_GEDBAS)) {

            if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_GEDBAS_UPLOAD, '0'))) {

                $export_file_name     = $filename . '.ged';
                $export_file_location = $this->gedcom_temp_path . $export_file_name;

                //Create resource and upload to GEDBAS
                try {
                    $resource = $this->filtered_gedcom_export_service->filteredResource(
                        $tree, true, $encoding, $privacy, $line_endings, $filename, $format, $gedcom_filter_set, $params, $clippings_cart_records, $export_clippings_cart);

                    //Upload to GEDBAS
                    $this->root_filesystem->writeStream($export_file_location, $resource);
                    $GEDBAS_Id = $this->uploadToGEDBAS($GEDBAS_apiKey, $GEDBAS_Id, $export_file_name, $export_file_location, $GEDBAS_title, $GEDBAS_description);
                    $this->root_filesystem->delete($export_file_location);
                } 
                catch (FilesystemException | UnableToWriteFile | GEDBASCommunicationException $ex) {

                    if ($ex instanceof GEDBASCommunicationException) {
                        $message = I18N::translate('Error during communication with GEDBAS'). ': ' . $ex->getMessage();
                    }
                    else {
                        $message =I18N::translate('The file %s could not be created.', $export_file_location);
                    }

                    if ($called_from_control_panel) {
                        FlashMessages::addMessage($message, 'danger');
                        return redirect(route(ExportGedcomPage::class, $parameters_for_control_panel));                 
                    }
                    else {
                        return $this->sendResponse($message, true, $called_from_control_panel);
                    }
                }

                //Assign received GEDBAS apiKey, title, Id, and description to tree
                $tree->setPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_APIKEY, $GEDBAS_apiKey);
                $parameters_for_control_panel['GEDBAS_Id'] = $GEDBAS_Id;

                if (!$export_clippings_cart) {
                    $tree->setPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_DESCRIPTION, $GEDBAS_description);
                    $tree->setPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_TITLE, $GEDBAS_title);
                    $tree->setPreference(DownloadGedcomWithURL::TREE_PREF_GEDBAS_ID, $GEDBAS_Id);
                }

                $message = I18N::translate('The family tree "%s" was sucessfully uploaded to GEDBAS', $tree_name);

                if ($called_from_control_panel) {
                    FlashMessages::addMessage($message, 'success');
                    return redirect(route(ExportGedcomPage::class, $parameters_for_control_panel));                 
                }
                else {
                    return $this->sendResponse($message, false, $called_from_control_panel);
                }
            }
            else {
                return $this->sendResponse( I18N::translate('Remote URL requests to upload GEDCOM files to GEDBAS are disabled.') . ' ' . 
                                                I18N::translate('Please check the module settings in the control panel.'),
                                                true, $called_from_control_panel);
            }
        }

        //If upload or convert is requested
        if ($action === self::ACTION_UPLOAD OR $action === self::ACTION_CONVERT) {

            // Save import choices as defaults
            if ($action === self::ACTION_UPLOAD) {
                $tree->setPreference('keep_media', $keep_media ? '1' : '0');
                $tree->setPreference('WORD_WRAPPED_NOTES', $word_wrapped_notes ? '1' : '0');
                $tree->setPreference('GEDCOM_MEDIA_PATH', $gedcom_media_path);     
            }

            if ($source === 'server') {

                try {
                    $resource = $this->root_filesystem->readStream($folder_on_server . $filename . $extension);
                    $stream = $this->stream_factory->createStreamFromResource($resource);
                }
                catch (Throwable $ex) {
                    $message = I18N::translate('Unable to read file "%s".', $folder_on_server . $filename . $extension);
                    return $this->sendResponse($message, true, $called_from_control_panel);
                }
            }
            elseif ($source === 'client') {
                $client_file = $request->getUploadedFiles()['client_file'] ?? null;

                if ($client_file === null || $client_file->getError() === UPLOAD_ERR_NO_FILE) {
                    $message = MoreI18N::xlate('No GEDCOM file was received.');    
                    return $this->sendResponse($message, true, $called_from_control_panel);
                }
    
                if ($client_file->getError() !== UPLOAD_ERR_OK) {
                    throw new FileUploadException($client_file);
                }
    
                try {
                    $stream    = $client_file->getStream(); 
                    $path_info = pathinfo($client_file->getClientFilename());
                    $extension = $path_info['extension'] ?? '';
                    $extension = $extension !== '' ? '.' . $extension : '';
                    $filename  = basename($client_file->getClientFilename(), $extension);
                }
                catch (Throwable $ex) {
                    $message = I18N::translate('Unable to read file "%s".', $client_file);
                    return $this->sendResponse($message, true, $called_from_control_panel);
                }   
            }
            else {
                $message = MoreI18N::xlate('No GEDCOM file was received.');
                return $this->sendResponse($message, true, $called_from_control_panel);
            }

            //Import the file to a set of Gedcom records
            try {
                $gedcom_records = $this->importGedcomFileToRecords($stream, $import_encoding, $word_wrapped_notes);

                if (empty($gedcom_records)) {
                    $message = I18N::translate('No data imported from file "%s". The file might be empty.', $filename . $extension);
                    return $this->sendResponse($message, true, $called_from_control_panel);
                }
                elseif ($action === self::ACTION_UPLOAD) {
                    $message = I18N::translate('The file "%s" was sucessfully uploaded for the family tree "%s"', $filename . $extension, $tree_name);
                    FlashMessages::addMessage($message, 'success');
                }
            }
            catch (Throwable $ex) {
                return $this->sendResponse($ex->getMessage(), true, $called_from_control_panel);
            } 

            //Apply Gedcom filters
            $matched_tag_combinations = [];
            $gedcom_records = $this->filtered_gedcom_export_service->applyGedcomFilters($gedcom_records, $gedcom_filter_set, $matched_tag_combinations, $params);
            
            if ($action === self::ACTION_CONVERT) {
                if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_CONVERT, '0'))) {                 

                    //Evaluate converted filename
                    if ($filename_converted === '') {
                        //Default, if no value received
                        $extension_converted =  $extension;                                
                        if ($source === 'client') {
                            $filename_converted = $filename;
                        }
                        else {
                            $filename_converted = $filename . '_converted';
                        }
                    }
                    else {
                        $file_info = $this->evaluateFilename($filename_converted, $action, $format);
                        $filename_converted = $file_info['filename'];
                        $extension_converted = $file_info['extension'];    
                    }   

                    if ($source === 'client') {
                        //Create a response from the filtered data
                        return $this->filtered_gedcom_export_service->filteredDownloadResponse(
                            $tree, true, $encoding, $privacy, $line_endings, $filename_converted, $extension_converted, $format, [], $params, new Collection($gedcom_records));
                    }
                    else {
                        //Download to the server

                        //Create response
                        $export_filename = $folder_on_server . $filename_converted . $extension_converted;
                        try {
                            //Create a stream from the filtered data
                            $resource = $this->filtered_gedcom_export_service->filteredResource(
                                $tree, true, $encoding, $privacy, $line_endings, $filename_converted, $format, [], [], new Collection($gedcom_records));

                            $this->root_filesystem->writeStream($export_filename, $resource);

                            $message = I18N::translate('The GEDCOM file "%s" was successfully converted to: %s', $filename . $extension, $export_filename);

                            if ($called_from_control_panel) {
                                FlashMessages::addMessage($message, 'success');
                                return redirect(route(ConvertGedcomPage::class, [
                                    'gedcom_filename'    => $filename,
                                    'filename_converted' => $filename_converted,
                                    'format'             => $format,
                                    'encoding'           => $encoding,
                                    'endings'            => $line_endings,
                                    'privacy'            => $privacy,
                                    'time_stamp'         => $time_stamp,
                                    'gedcom_filter1'     => $gedcom_filter1,
                                    'gedcom_filter2'     => $gedcom_filter2,
                                    'gedcom_filter3'     => $gedcom_filter3,
                                ]));
                            }
                            else {
                                return $this->sendResponse($message, false, $called_from_control_panel);
                            }
                        } 
                        catch (FilesystemException | UnableToWriteFile | DownloadGedcomWithUrlException $ex) {
        
                            if ($ex instanceof DownloadGedcomWithUrlException) {
                                return $this->sendResponse($ex->getMessage(), true, $called_from_control_panel);
                            }
                            else {
                                return $this->sendResponse(I18N::translate('The file %s could not be created.', $folder_on_server . $export_filename), true, $called_from_control_panel);
                            }
                        }        
                    }
                }
                else {
                    return $this->sendResponse( I18N::translate('Remote URL requests to convert GEDCOM files on the server are not allowed.') . ' ' . 
                                                    I18N::translate('Please check the module settings in the control panel.'),
                                                    true, $called_from_control_panel);
                }
            }

            if ($action === self::ACTION_UPLOAD) {
                if ($called_from_control_panel OR boolval($this->getPreference(self::PREF_ALLOW_REMOTE_UPLOAD, '0'))) {

                    //Create a stream from the filtered data
                    $resource = $this->filtered_gedcom_export_service->filteredResource(
                        $tree, true, $encoding, $privacy, $line_endings, $filename, $format, [], [], new Collection($gedcom_records));

                    try {
                        $file_info = $this->evaluateFilename($filename, $action, $format);
                        $temporary_folder = $folder_on_server . self::UPLOAD_TEMP_FOLDER;

                        while ($this->root_filesystem->directoryExists($temporary_folder)) {
                            $temporary_folder .= 'x';
                        }

                        $temporary_file = $temporary_folder . $file_info['filename'] . $file_info['extension']; 

                        //Save the filtered export to a temporary file
                        $this->root_filesystem->writeStream($temporary_file, $resource);

                        //Import the tree into the database
                        $this->importTree($tree, Webtrees::ROOT_DIR . $temporary_file, $encoding, $keep_media, $word_wrapped_notes, $gedcom_media_path);

                        //Delete the temporary folder
                        $this->root_filesystem->deleteDirectory($temporary_folder);
                    }
                    catch (Throwable $ex) {
                        return $this->sendResponse($ex->getMessage(), true, $called_from_control_panel);
                    }

                    //Successfully return after upload/import
                    $message = I18N::translate('The tree was successfully imported into the database.');

                    if ($called_from_control_panel) {
                        FlashMessages::addMessage($message, 'success');
                        return redirect(route(ImportGedcomPage::class, [
                            'tree'            => $tree->name(),
                            'gedcom_filename' => $filename,
                            'gedcom_filter1'  => $gedcom_filter1,
                            'gedcom_filter2'  => $gedcom_filter2,
                            'gedcom_filter3'  => $gedcom_filter3,
                        ]));
                    }
                    else {
                        return $this->sendResponse($message, false, $called_from_control_panel);
                    }                    
                }
                else {
                    return $this->sendResponse( I18N::translate('Remote URL requests to upload GEDCOM files to the server are not allowed.') . ' ' . 
                                                    I18N::translate('Please check the module settings in the control panel.'),
                                                    true, $called_from_control_panel);
                }
            }
        }

        return $this->sendResponse(I18N::translate('Reached end of request handle without proper response'), true, $called_from_control_panel);
    }
}
