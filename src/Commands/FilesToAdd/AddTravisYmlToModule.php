<?php

namespace Sunnysideup\ModuleChecks\Commands\FilesToAdd;

use Sunnysideup\ModuleChecks\Commands\AddFileToModule;

class AddTravisYmlToModule extends AddFileToModule
{
    protected $sourceLocation = 'app/template_files/.travis.yml';

    protected $fileLocation = '.travis.yml';
}