<?php

namespace Sunnysideup\ModuleChecks\BaseCommands\RunCommandLineMethodOnModule;

use SilverStripe\View\ViewableData;

abstract class RunCommandLineMethodOnModule
{
    /**
     * root dir for module
     * e.g. /var/www/modules/mymodule
     * no final slash
     *
     * @var string
     */
    protected $rootDirForModule = '';

    /**
     * @var string
     */
    protected $commands = [];

    public function __construct($rootDirForModule = '')
    {
        $this->rootDirForModule = $rootDirForModule;
    }

    public function setRootDirForModule($rootDirForModule)
    {
        $this->{$rootDirForModule} = $rootDirForModule;
    }

    /**
     * @param string $commands
     */
    public function setCommand(array $commands)
    {
        $this->commands = $commands;

        return $this;
    }

    /**
     * @param string $command
     */
    public function addCommands(string $command)
    {
        $this->commands[] = $command;

        return $this;
    }

    public function run()
    {
        if (! $this->rootDirForModule) {
            user_error('no root dir for module has been set');
        }
        if (! count($this->commands)) {
            user_error('command not set');
        }
        $this->runCommand();
    }

    public static function CheckCommandExists($cmd)
    {
        return ! empty(shell_exec("which ${cmd}"));
    }

    /**
     * runs a command from the root dir or the module
     */
    protected function runCommand()
    {
        foreach ($this->commands as $command) {
            GeneralMethods::output_to_screen('Running ' . $command);
            return exec(
                ' cd ' . $this->rootDirForModule . ';
                ' . $command . '
                '
            );
        }
    }
}