<?php

namespace Sunnysideup\ModuleChecks\FilesToAdd;

use Sunnysideup\ModuleChecks\BaseCommands\AddFileToModule;

class AddScrutinizerYmlToModule extends AddFileToModule
{
    protected $sourceLocation = 'app/template_files/.scrutinizer.yml';

    protected $fileLocation = '.scrutinizer.yml';
}
