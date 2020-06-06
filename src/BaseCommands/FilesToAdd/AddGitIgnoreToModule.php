<?php

namespace Sunnysideup\ModuleChecks\BaseCommands\FilesToAdd;

use Sunnysideup\ModuleChecks\BaseCommands\AddFileToModule;

class AddGitIgnoreToModule extends AddFileToModule
{
    protected $sourceLocation = 'app/template_files/.gitignore';

    protected $fileLocation = '.gitignore';
}