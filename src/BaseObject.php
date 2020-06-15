<?php

namespace Sunnysideup\ModuleChecks;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Director;
use Sunnysideup\Flush\FlushNow;
use Sunnysideup\ModuleChecks\Model\Check;
use Sunnysideup\ModuleChecks\Model\Module;
use Sunnysideup\ModuleChecks\Commands\LastAbstract;
use Sunnysideup\ModuleChecks\Commands\FirstAbstract;
use Sunnysideup\ModuleChecks\Commands\ChecksAbstract;
use Sunnysideup\ModuleChecks\Commands\FilesToAddAbstract;
use Sunnysideup\ModuleChecks\Commands\ShellCommandsAbstract;
use Sunnysideup\ModuleChecks\Commands\OtherCommandsAbstract;
use Sunnysideup\ModuleChecks\Commands\UpdateComposerAbstract;

class BaseObject
{
    use Extensible;
    use Injectable;
    use Configurable;
    use FlushNow;

    private const CHECKS = [
        'github_account_base_url',
        'github_user_name',
        'github_user_email',
        'path_to_private_key',
        'absolute_temp_folder',
    ];

    protected static $inst = null;


    /**
     * list of classes to run,  in the right order
     * @var array
     */
    private static $core_classes = [
        FirstAbstract::class,
        ChecksAbstract::class,
        FilesToAddAbstract::class,
        UpdateComposerAbstract::class,
        ShellCommandsAbstract::class,
        OtherCommandsAbstract::class,
        LastAbstract::class,
    ];

    /**
     * e.g.
     * @var string
     */
    private static $github_account_base_url = '';

    /**
     * e.g. boss
     * @var string
     */
    private static $github_user_name = '';

    /**
     * @var string
     */
    private static $github_user_email = '';

    /**
     * @var string
     */
    private static $path_to_private_key = 'certs/id_rsa';

    /**
     * @var string
     */
    private static $packagist_user_name = '';

    private static $scrutinizer_api_key = '';

    /**
     * where the git module is temporary
     * cloned and fixed up
     * should be an absolute_path
     *
     * @var string
     */
    private static $absolute_temp_folder = '/var/www/temp/';

    /**
     * @var string
     */
    private static $license_type = 'BSD-3-Clause';

    /**
     * Auto tag creation delay, using strtotime format. defaults to a week ago if not set
     * @var string
     */
    private static $tag_delay = '-1 weeks';

    /**
     *  message for auto-created tags (git tag!)
     * @var string
     */
    private static $tag_create_message = 'Auto-created tag.';

    /**
     * log folder is needed to write log file with unresolved problems, leave out
     * not to write log file
     * @var string
     */
    private static $logfolder = '/var/www/moduletools/log/';

    /**
     *  Words to check for accross all files in test modules. Produces warnings
     *  when matches are found. Regex fomat. Leave empty not to do any checks
     */
    private static $excluded_words = [

    ];

    private static $home_page = 'https://silverstripe.org';

    private static $debug = true;

    public static function inst(): BaseObject
    {
        if (! self::$inst) {
            self::$inst = Injector::inst()->get(BaseObject::class);
            self::$inst->areWeReady();
        }
        return self::$inst;
    }

    public function areWeReady()
    {
        foreach (self::CHECKS as $check) {
            $value = $this->Config()->get($check);
            if (! $value) {
                user_error('You need to set ' . $check . ' as a private static in BaseObject');
                return false;
            }
            if (strpos($value, '/') !== false) {
                if (! file_exists($value)) {
                    $fullFile = Director::baseFolder().'/'.$value;
                    if(file_exists($fullFile) || is_link($fullFile))  {
                        //all good...
                    } else {
                        user_error('The following dir/file can not be found! ' . Director::baseFolder().'/'.$value);
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /*
     * This function is just used to suppression of warnings
     * */
    private function catchFopenWarning($errno, $errstr)
    {
    }
}
