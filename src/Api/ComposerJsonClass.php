<?php

namespace Sunnysideup\ModuleChecks\Api;

use SilverStripe\Core\ClassInfo;
use Sunnysideup\ModuleChecks\BaseObject;
use Sunnysideup\ModuleChecks\Commands\UpdateComposerAbstract;
use Sunnysideup\ModuleChecks\Model\Module;
use Sunnysideup\ModuleChecks\Tasks\UpdateModules;


class ComposerJsonClass extends BaseObject
{

    /**
     * @var array|null
     */
    protected $jsonData = null;

    /**
     * @var Module|null
     */
    protected $repo = null;

    /**
     * @var Module|null
     */
    protected $moduleName = '';

    public function __construct($repo)
    {
        $this->repo = $repo;
        $this->moduleName = $this->repo->ModuleName;
    }

    /**
     * @return array
     */
    public function getJsonData(): array
    {
        return $this->jsonData;
    }

    /**
     * @param array $array [description]
     */
    public function setJsonData(array $array)
    {
        $this->jsonData = $array;

        return $this;
    }

    public function getDescription(): string
    {
        if (! is_array($this->jsonData)) { //if not loaded
            return 'no json data';
        }

        return isset($this->jsonData['description']) ? $this->jsonData['description'] : 'description tba';
    }

    protected function writeJsonToFile(): bool
    {
        if (! is_array($this->jsonData)) { //if not loaded
            return false;
        }

        $folder = Config::inst()->get(BaseObject::class, 'absolute_temp_folder');
        $filename = $folder . '/' . $this->repo->ModuleName . '/composer.json';
        $value = file_put_contents($filename, json_encode($this->jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $value ? true : false;
    }

    private function readJsonFromFile(): bool
    {
        $folder = Config::inst()->get(BaseObject::class, 'absolute_temp_folder');
        $filename = $folder . '/' . $this->moduleName . '/composer.json';
        set_error_handler([$this, 'catchFopenWarning'], E_WARNING);

        $json = file_get_contents($filename);
        restore_error_handler();
        if ($json) {
            $this->jsonData = json_decode($json, true);
        } else {
            UpdateModules::addUnsolvedProblem($this->moduleName, 'Could not open composer.json file...');
        }
        return is_array($this->jsonData);
    }

    private function catchFopenWarning()
    {
        user_error('Can not open composer file ....');
    }

    // public function updateJsonFile()
    // {
    //     if (! is_array($this->jsonData)) {
    //         $this->readJsonFromFile();
    //     }
    //
    //     if (is_array($this->jsonData)) {
    //         FlushNow::flushNow('Updating composer.json');
    //         $composerUpdates = ClassInfo::subclassesFor(UpdateComposerAbstract::class);
    //
    //         //remove base class
    //         array_shift($composerUpdates);
    //
    //         //get all updates and if they exists then get the ones that we need to do ...
    //         $limitedComposerUpdates = $this->Config()->get('updates');
    //         if ($limitedComposerUpdates === 'none') {
    //             $composerUpdates = [];
    //         } elseif (is_array($limitedComposerUpdates) && count($limitedComposerUpdates)) {
    //             $composerUpdates = array_intersect($composerUpdates, $limitedComposerUpdates);
    //         }
    //
    //         foreach ($composerUpdates as $composerUpdate) {
    //             $obj = $composerUpdate::create($this);
    //             $obj->run();
    //         }
    //         if ($this->writeJsonToFile()) {
    //             FlushNow::flushNow('Updated JSON </li>');
    //         } else {
    //             UpdateModules::addUnsolvedProblem($this->moduleName, 'Could not write JSON');
    //         }
    //     } else {
    //         //UpdateModules::$unsolvedItems[$this->moduleName] = 'No composer.json';
    //
    //         UpdateModules::addUnsolvedProblem($this->moduleName, 'No composer.json');
    //     }
    //
    //     return $this;
    // }
}
