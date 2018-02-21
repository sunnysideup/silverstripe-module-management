<?php

/**
 * does everything with one module
 *
 * see https://github.com/cpliakas/git-commsWrapper for info on the git commsWrapper module
 */


use GitWrapper\GitWrapper;
require_once '../vendor/autoload.php';

class GitHubModule extends DataObject {



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
     *
     *
     * @var GitcommsWrapper
     */
    private static $git_user_email = '';

    /**
     * where the git module is temporary
     * cloned and fixed up
     * should be an absolute_path
     *
     * @var string
     */
    private static $path_to_private_key = '';

    /**
     * where the git module is temporary
     * cloned and fixed up
     * should be an absolute_path
     *
     * @var string
     */
    private static $absolute_temp_folder = '';

    /**
     * wrapper also relates to one git hub repo only!!!!
     *
     * @var GitcommsWrapper
     */
    protected $commsWrapper = null;

    /**
     *
     *
     * @var git module
     */
    protected $gitRepo = null;

    private static $db = array (
        'ModuleName' => 'VarChar(100)',
        'Description' => 'VarChar(300)'
    );


    private static $indexes = array (
        'ModuleName' => true,
    );


    private static $casting = array(
        'Directory' => 'Varchar(255)',
        'URL' => 'Varchar(255)',
    );

    public function getDirectory() {
        return $this->Directory();
    }

    public function getDescription() {
        return $this->Description();
    }
    /**
     * absolute path
     * @return string | null
     */
    public function Directory () {
        $tempFolder = $this->Config()->get('absolute_temp_folder');
        if($this->ModuleName) {
            $folder = $tempFolder.'/'.$this->ModuleName;
            if(file_exists($folder)) {
                if(file_exists($folder)) {
                    return $folder;
                }
            } else {
                mkdir($folder);
                if(file_exists($folder)) {
                    return $folder;
                }
            }
        }
    }

    public function getURL()
    {
        return $this->URL();
    }


    function LongModuleName()
    {
        return $this->Config()->get('git_user_name').'/'.$this->ModuleName;
    }


    function MediumModuleName()
    {
        return $this->ModuleName;
    }

    /**
     * @todo: check that silverstripe- is at the start of string.
     * @return string
     */
    function ShortModuleName()
    {
        return str_replace('silverstripe-', '', $this->ModuleName);
    }

    function ShortUCFirstName()
    {
        $array = explode ('_', $this->ShortModuleName());

        $name = '';

        foreach ($array as $part)
        {

            $name .= ucfirst ($part);

        }

        return $name;
    }


    function ModuleNameFirstLetterCapital() {
        $shortName = $this->ShortModuleName();

        $firstLetterCapitalName = str_replace ('_', ' ', $shortName);
        $firstLetterCapitalName = str_replace ('-', ' ', $firstLetterCapitalName);


        return strtolower ($firstLetterCapitalName);
    }

    function setDescription($str) {
        $this->Description = trim($str);
    }

    /**
     * check if URL exists and returns it
     * @var string | null
     */
    public function URL () {
        $username = $this->Config()->get('git_user_name');
        return 'https://github.com/'.$username.'/'.$this->ModuleName;
    }

    protected function IsDirGitRepo ($directory) {
        return file_exists($directory."/.git");
    }


    /**
     *
     * @param bool (optional) $forceNew - create a new repo and ditch all changes
     * @return Git Repo Object
     */
    public function checkOrSetGitCommsWrapper($forceNew = false) {
        //check if one has been created already...
        if( ! $this->gitRepo ) {

            //basic check
            if ($this->ModuleName == '') {
                user_error('ModuleName element must be set before using git repository commands');
            }

            //create comms
            $this->commsWrapper = new GitWrapper();

            // Stream output of subsequent Git commands in real time to STDOUT and STDERR.
            if(Director::is_cli()) {
                $this->commsWrapper->streamOutput();
            }


            if( ! $this->Config()->get('path_to_private_key')) {
                user_error("We recommend you set private key");
            }
            // Optionally specify a private key other than one of the defaults.
            $this->commsWrapper->setPrivateKey($this->Config()->get('path_to_private_key'));

            //if directory exists, return existing repo,
            //otherwise clone it....
            if($this->IsDirGitRepo($this->Directory())) {
                if($forceNew) {
                    $this->removeClone();
                    return $this->checkOrSetGitCommsWrapper(false);
                }
                $this->gitRepo = $this->commsWrapper->workingCopy($this->Directory());
            } else {
                GeneralMethods::output_to_screen("cloning ... ".$this->fullGitURL(),'created');

                $this->gitRepo = null;
                $cloneAttempts = 0;
                while ( ! $this->gitRepo ) {
                    $cloneAttempts ++;
                    if ($cloneAttempts == 4) {
                        user_error ('Failed to clone module ' . $this->LongModuleName() . ' after ' . ($cloneAttempts  - 1). ' attemps.', E_USER_ERROR);
                        //UpdateModules::$unsolvedItems[$this->ModuleName()] = 'Failed to clone modules';
                        UpdateModules::addUnsolvedProblem($this->ModuleName() , 'Failed to clone modules');

                    }
                    try {
                        $this->commsWrapper->setTimeout(240); //Big modules need a longer timeout
                        $this->gitRepo = $this->commsWrapper->cloneRepository(
                            $this->fullGitURL(),
                            $this->Directory()
                        );
                        $this->commsWrapper->setTimeout(60);
                    }
                    catch (Exception $e) {
                        if (strpos($e->getMessage(), 'already exists and is not an empty directory') !== false) {
                            user_error ($e->getMessage(), E_USER_ERROR);
                        }

                        GeneralMethods::outputToScreen ('<li>Failed to clone repository: ' .  $e->getMessage() . '</li>');
                        GeneralMethods::outputToScreen ('<li>Waiting 8 seconds to try again ...: </li>');
                        $this->removeClone();
                        sleep (8);
                    }
                }
            }
            $this->gitRepo->config("push.default", "simple");
            $this->gitRepo->config("user.name", $this->Config()->get('git_user_name'));
            $this->gitRepo->config("user.email", $this->Config()->get('git_user_email'));
            $this->commsWrapper->git('config -l');
        }
        return $this->gitRepo;
    }

    /**
     * @var string
     */
    function fullGitURL()
    {
        $username = $this->Config()->get('git_user_name');
        $gitURL = $this->Config()->get('github_account_base_url');
        return 'git@github.com:/'.$username.'/'.$this->ModuleName.'.git';
    }

    /**
     * pulls a git repo
     *
     * @return bool | this
     */
    public function pull() {
        $git = $this->checkOrSetGitCommsWrapper();
        if ($git) {
            try {
                 $git->pull();
            }
            catch (GitWrapper\GitException $e) {
                print_r($e);
                throw $e;
            }


            //GeneralMethods::output_to_screen($git->getOutput());
            return $this;
        }
        return false;
    }

    /**
     * commits a git repo
     *
     * @param string $message
     *
     * @return bool | this
     */
    public function commit($message = 'PATCH: module clean-up') {

        $git = $this->checkOrSetGitCommsWrapper();
        if ($git) {

            try {
                $git->commit($message);
            }
            catch (Exception $e) {
                $errStr = $e->getMessage();
                if (stripos($errStr, 'nothing to commit') === false) {
                    print_r($e);
                    throw $e;
                }
                else {
                    GeneralMethods::output_to_screen('No changes to commit');
                }
            }
            //GeneralMethods::output_to_screen($git->getOutput());

            return $this;
        }
        return false;
    }

    /**
     * adds all files to a git repo
     * @return bool | this
     */
    public function add() {

        GeneralMethods::output_to_screen('Adding new files to '.$this->ModuleName.' ...  ' ,"created");

        $git = $this->checkOrSetGitcommsWrapper();
        if ($git) {
            try {
                $git->add(".");
            }
            catch (GitWrapper\GitException $e) {
                $errStr = $e->getMessage();
                if (stripos($errStr, 'did not match any files') === false) {
                    print_r($e);
                    throw $e;
                }
                else {
                   GeneralMethods::output_to_screen('No new files to add to $module. ');
                }
            }

            //GeneralMethods::output_to_screen($git->getOutput());

            return $this;
        }
        return false;
    }

    /**
     * adds all files to a git repo
     *
     * @return bool | this
     */
    public function push() {
        GeneralMethods::output_to_screen('Pushing files to '.$this->ModuleName.' ...  ' ,"created");

        $git = $this->checkOrSetGitcommsWrapper();
        if ($git) {
            $pushed = false;
            $pushAttempts = 0;
            while ( ! $pushed ) {
                $pushAttempts ++;
                try {
                    $git->push();
                    $pushed = true;
                }
                catch (Exception $e) {

                    if ($pushAttempts == 3) {
                        $git->getOutput();
                        print_r($e);
                        throw $e;
                    }
                    else {
                        GeneralMethods::outputToScreen ('<li>Failed to push repository: ' .  $e->getMessage() . '</li>');
                        GeneralMethods::outputToScreen ('<li>Waiting 8 seconds to try again ...: </li>');
                        sleep (8);
                    }
                }
            }
            return $this;
        }
        return false;
    }

    /**
     * removes a cloned repo
     *
     *
     */
    public function removeClone() {
        $dir = $this->Directory();
        GeneralMethods::output_to_screen('Removing '.$dir.' and all its contents ...  ' ,"created");
        $this->gitRepo = null;
        FileSystem::removeFolder($dir); // removes contents but not the actual folder
        //rmdir ($dir);
        return ! file_exists($dir);
    }

    /**
     * retrieves a raw file from Github
     *
     * @return string | bool
     */

    public function getRawFileFromGithub($fileName) {

        $gitUserName = $this->Config()->get('git_user_name');
        $branch = 'master';

        $rawURL = 'https://raw.githubusercontent.com/' . $gitUserName . '/' . $this->ModuleName . '/' . $branch . '/' . $fileName;

        set_error_handler(array($this, 'catchFopenWarning'), E_WARNING);
        $file = fopen($rawURL, 'r');
        restore_error_handler();

        if ( ! $file){
            GeneralMethods::outputToScreen('<li>Could not find ' . $rawURL . '</li>');
            return false;
        }
        $content = '';
        while(! feof($file))
        {
            $content .= fgets($file);
        }
        fclose($file);
        return $content;
    }

    /*
     * This function is just used to suppression of warnings
     * */
    private function catchFopenWarning($errno, $errstr) {
        //
    }


    public static function get_or_create_github_module($moduleName) {
        $moduleName = trim($moduleName);
        $filter = array('ModuleName' => $moduleName);
        $gitHubModule = GitHubModule::get()->filter($filter)->first();
        if ( ! $gitHubModule) {
            $gitHubModule = GitHubModule::create($filter);
            $gitHubModule->write();
        }

        return $gitHubModule;
    }


    public function getLatestCommitTime() {
        // equivalent to git log -1 --format=%cd .

        $git = $this->checkOrSetGitCommsWrapper();
        if ($git) {
            $options = array (
                'format' => "%cd",
                '1' => true
            );

            try {
                $result = $git->log ($options);
            }
            catch (Exception $e) {
                $errStr = $e->getMessage();
                if (stripos($errStr, 'does not have any commits') === false) {
                    print_r($e);
                    throw $e;
                }
                else {

                    return false;
                }
            }

            if ($result) {
                return (strtotime($result));
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }

    private $_latest_tag = null;

    public function getLatestTag() {
        if($this->_latest_tag === null) {
            $git = $this->checkOrSetGitCommsWrapper();
            if ($git) {
                $options = array (
                    'tags' => true,
                    'simplify-by-decoration' => true,
                    'pretty' => 'format:%ai %d'
                );

                $cwd = getcwd();
                chdir($this->Directory);

                try {
                    $result = $git->log($options);
                }
                catch (Exception $e) {
                    $errStr = $e->getMessage();
                    if (stripos($errStr, 'does not have any commits') === false) {
                        print_r($e);
                        throw $e;
                    }
                    else {
                        GeneralMethods::output_to_screen('Unable to get tag because there are no commits to the repository');
                        return false;
                    }
                }


                chdir($cwd);

                $resultLines  =  explode ("\n", $result->getOutput());

                // 2016-10-14 12:29:08 +1300 (HEAD -> master, tag: 2.3.0, tag: 2.2.0, tag: 2.1.0, origin/master, origin/HEAD)\
                // or
                // 2016-08-29 17:18:22 +1200 (tag: 2.0.0)
                //print_r($resultLines);


                if (count($resultLines) == 0) {
                    return false;
                }

                $latestTimeStamp = 0;
                $latestTag = false;
                foreach ($resultLines as $line) {
                    $isTagInLine = (strpos ($line, 'tag') !== false);
                    if ($isTagInLine) {
                        $tagStr = trim(substr($line, 25));
                        $dateStr = trim(substr($line, 0, 26));


                        //extract tag numbers from $tagStr

                        $matches = array();
                        // print_r ("original!!! " .  $tagStr);
                        $result = preg_match_all('/tag: \d{1,3}.\d{1,3}.\d{1,3}/', $tagStr, $matches);
                        if ($result === false)
                        {
                            continue;
                        }
                        elseif ($result > 1)
                        {
                            $tagStr = $matches[0][0];
                        }
                        //print_r ($matches);

                        $tagStr  = str_replace('(', '', $tagStr);
                        $tagStr  = str_replace(')', '', $tagStr);
                        $timeStamp = strtotime ($dateStr);

                        if ($latestTimeStamp < $timeStamp) {
                            $latestTimeStamp = $timeStamp;
                            $latestTag = $tagStr;
                        }
                    }
                }
                if (isset($latestTag) && $latestTag) {
                    $latestTag = str_replace('tag:', '', $latestTag) ;


                    $tagParts = explode ('.', $latestTag);

                    if (count($tagParts) != 3) return false;
                    $this->_latest_tag = array (
                        'tagstring' => $latestTag,
                        'tagparts' => $tagParts,
                        'timestamp' => $latestTimeStamp);

                }
                else {
                    $this->_latest_tag = false;
                }

            }
        }
        return $this->_latest_tag;
    }

    /**
     * git command used: //git log 0.0.1..HEAD --oneline
     * return @string (major | minor | patch)
     */
    public function getChangeTypeSinceLastTag()
    {
        $latestTag = trim($this->getLatestTag()['tagstring']);

        $git = $this->checkOrSetGitCommsWrapper();
        if ($git) {

			//var_dump ($git);
			//die();

            $options = array (
                'oneline' => true
            );

            $cwd = getcwd();
            chdir($this->Directory);

            try {
                $result = $git->log($latestTag.'..HEAD', $options);
                // print_r($latestTag);
                // print_r($result);
				if(!is_array($result)) {
					$result = explode("\n", $result);
				}
				// print_r ($result);
            }
            catch (Exception $e) {
                $errStr = $e->getMessage();
                GeneralMethods::output_to_screen('Unable to get next tag type (getChangeTypeSinceLastTag)');
                return false;
            }

            chdir($cwd);
            $returnLine = 'PATCH';




            foreach($result as $line) {
                if(stripos($line, 'MAJOR:') !== false) {
                    $returnLine = 'MAJOR';
                    break;
                }
                if(stripos($line,  'MINOR:') !== false) {
                    $returnLine = 'MINOR';
                }
            }
            return $returnLine;
        }

    }

    public function createTag($tagCommandOptions)
    {
        $this->gitRepo->tag($tagCommandOptions);
        $this->gitRepo->push(array('tags' => true));

    }

    public function updateGitHubInfo($array) {
        // see https://developer.github.com/v3/repos/#edit

        # not working

        $defaultValues =array(
            'name' => $this->LongModuleName(),
            'private' => false,
            'has_wiki' => false,
            'has_issues' => true,
            'has_downloads' => true,
            'homepage' => 'http://ssmods.com/'
            );

        if ($this->Description) {
            $array['description'] = $this->Description;
        }

        foreach ($defaultValues as $key=>$value) {
            if (! isset($array[$key])) {
                $array[$key]  = $value;
            }
        }

        GeneralMethods::OutputToScreen('updating Git Repo information ...');

        $this->gitApiCall($array, '',  'PATCH');
    }

    protected function gitApiCall($data, $gitAPIcommand = '', $method = 'GET') {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        GeneralMethods::OutputToScreen('Running Git API command ' .$gitAPIcommand. ' using ' .$method. ' method...');
        $gitUserName = $this->Config()->get('git_user_name');
        $url = 'https://api.github.com/:repos/' . trim($gitUserName) . '/:' . trim($this->ModuleName);
        if (trim($gitAPIcommand)) {
            $url .= '/' . trim($gitAPIcommand);
        }
        $method = trim(strtoupper($method));
        $ch = curl_init($url);
        $header = "Content-Type: application/json";
        if ($method == 'GET') {
            $url .= '?'.http_build_query($data);
        }
        $gitApiUserName = trim($this->Config()->get('git_api_login_username'));
        $gitApiUserPassword = trim($this->Config()->get('git_api_login_password'));
        $gitApiAccessToken = trim($this->Config()->get('git_personal_access_token'));
        if (trim($gitApiAccessToken)) {
            $gitApiUserPassword = $gitApiAccessToken;
        }
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($header));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT,
            'Silverstripe-update-module-module');
        if (isset($gitApiUserName) && isset($gitApiUserPassword)) {
            curl_setopt($ch, CURLOPT_USERPWD, $gitApiUserName . ':' . $gitApiUserPassword);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        if ($method == 'POST' ) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }
        $curlResult = curl_exec($ch);
        if ( ! $curlResult ){
            $msg = "curl exectution failed";
            GeneralMethods::outputToScreen ($msg);
            UpdateModules::$unsolvedItems["none"] = $msg;
        }
        print_r($url);
        print_r('<br/>');
        print_r($curlResult );
        die();
        return $curlResult;
    }


    public static function getRepoList() {


        $gitUserName = GitHubModule::Config()->get('git_user_name');
        $url = 'https://api.github.com/users/' . trim($gitUserName) . '/repos';
        $array  = array();
        for($page = 0; $page < 10; $page++) {

            $data = array(
                'per_page' => 100,
                'page'=>$page
            );

            $method = 'GET';
            $ch = curl_init($url);
            $header = "Content-Type: application/json";

            if ($method == 'GET') {
                $url .= '?'.http_build_query($data);
            }

            $gitApiUserName = trim(GitHubModule::Config()->get('git_api_login_username'));
            $gitUserName = trim(GitHubModule::Config()->get('git_user_name'));
            $gitApiUserPassword = trim(GitHubModule::Config()->get('git_api_login_password'));

            $gitApiAccessToken = trim(GitHubModule::Config()->get('git_personal_access_token'));
            if (trim($gitApiAccessToken)) {
                $gitApiUserPassword = $gitApiAccessToken;
            }


            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($header));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_USERAGENT,
                'sunnysideupdevs');


            if (isset($gitApiUserName) && isset($gitApiUserPassword)) {
                curl_setopt($ch, CURLOPT_USERPWD, $gitApiUserName . ':' . $gitApiUserPassword);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }


            $curlResult = curl_exec($ch);

            if ( ! $curlResult ){
                GeneralMethods::OutputToScreen('Could not retrieve list of modules from GitHub');

                UpdateModules::$unsolvedItems["all"] =  ('Could not retrieve list of modules from GitHub');
                die ('');
            }

            $array = array_merge( $array, json_decode($curlResult));
        }


        $modules = array();

        if(count($array) > 0 )

         {
                    foreach($array as $repo) {

                        // see http://stackoverflow.com/questions/4345554/convert-php-object-to-associative-array
                        $repo = json_decode(json_encode($repo), true);


                        //dont bother about forks
                        if(isset($repo["fork"]) && !$repo["fork"]) {
                            //make sure we are the owners

                            if($repo["owner"]["login"] == $gitUserName) {

                                $isSSModule =  ( stripos($repo["name"], 'silverstripe-')  !== false );
                                //check it is silverstripe module
                                $getNamesWithPrefix = false;

                                if (!$getNamesWithPrefix) {
                                    $name = $repo["name"];
                                }
                                else {
                                    $name = preg_replace('/silverstripe/', "", $repo["name"], $limit = 1);
                                }

                                //if(strlen($name) < strlen($repo["name"])) {
                                if($isSSModule) {
                                    //is it listed yet?
                                    if(!in_array($name, $modules)) {

                                        array_push ($modules, $name);
                                    }
                                }
                                else {
                                    GeneralMethods::OutputToScreen("skipping ".$repo["name"]." as it does not appear to me a silverstripe module");
                                }
                            }
                            else {
                                GeneralMethods::OutputToScreen("skipping ".$repo["name"]." as it has a different owner");
                            }
                        }
                        elseif(isset($repo["name"])) {
                            DB::alteration_message("skipping ".$repo["name"]." as it is a fork");
                        }
                    }
                }

        return $modules;
    }

    public function addRepoToScrutinzer () {

        if (! trim($this->Config()->get('scrutinizer_api_key'))) {
            GeneralMethods::outputToScreen ("<li> not Scrutinizer API key set </li>");
            return false;
        }

        //see https://scrutinizer-ci.com/docs/api/#repositories
        $scrutinizerApiPath = "https://scrutinizer-ci.com/api";
        $endPoint = "repositories/g?access_token=" . trim($this->Config()->get('scrutinizer_api_key'));
        $url = $scrutinizerApiPath . "/" . $endPoint;
        $username = $this->Config()->get('git_user_name');
        $repoName =  $username.'/'.$this->ModuleName;


        $postFields = array (
            'name' => $repoName,
            );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, count($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        $curlResult = curl_exec($ch);

        if ( ! $curlResult ){
            GeneralMethods::outputToScreen ("<li> could not add $repoName to Scrutinizer ... </li>");
            //UpdateModules::$unsolvedItems[$repoName] = "Could not add $reopName to Scrutiniser (curl failure)";

            UpdateModules::addUnsolvedProblem($repoName, "Could not add $repoName to Scrutiniser (curl failure)");

            return false;
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        if ($httpcode == 201) {
            GeneralMethods::outputToScreen ("<li> Added $repoName to Scrutinizer ... </li>");
        }
        else{
            GeneralMethods::outputToScreen ("<li> could not add $repoName to Scrutinizer ... </li>");
            //UpdateModules::$unsolvedItems[$repoName] = "Could not add $reopName to Scrutiniser (HttpCode $httpcode)";
            UpdateModules::addUnsolvedProblem($repoName, "Could not add $repoName to Scrutiniser (HttpCode $httpcode)");
        }



    }

}