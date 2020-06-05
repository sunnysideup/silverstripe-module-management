<?php

namespace Sunnysideup\ModuleChecks\FilesToAdd;

use AddFileToModule;


class AddManifestExcludeToModule extends AddFileToModule
{
    protected $sourceLocation = 'app/template_files/_module_data/_manifest_exclude';

    protected $fileLocation = '_module_data/_manifest_exclude';
}
