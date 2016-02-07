<?php
namespace Quazardous;
use Garden\Cli\Cli;
use Garden\Cli\Args;

class BumpVersion
{
    const CONFIG_FILE = '.bump-version.php';
    
    protected $argv;
    public function __construct($argv = null)
    {
        if ($argv) {
            $this->argv = $argv;
        } else {
            $this->argv = $_SERVER['argv'];
        }
    }
    
    public function run()
    {
        if (count($this->argv) == 1) {
            $this->help();
            return false;
        }
        $this->init();
        
        $cli = new Cli();
        $command = strtolower($this->argv[1]);
        array_splice($this->argv, 1, 1);
        switch ($command) {
            case 'bump':
                $this->argv[0] .= ' bump';
                $cli->description('Bump version')
                    ->opt('patch:p', 'Bump a patch version.', false, 'bool')
                    ->opt('minor:m', 'Bump a minor version.', false, 'bool')
                    ->opt('major:M', 'Bump a major version.', false, 'bool')
                    ->opt('check:c', 'Checks stuff', false, 'bool')
                    ->opt('version:v', 'Force set version');
                if (count($this->argv) == 1) {
                    $this->help($cli);
                    return false;
                }
                return $this->bumbCommand($cli->parse($this->argv, false));
            case 'merge_into':
                $this->argv[0] .= ' merge_into';
                $cli->description('Merge current branch into given branch')
                    ->arg('target', 'Branch to merge into', true)
                    ->opt('paranoid:P', 'Be extra cautious.', false, 'bool');
                if (count($this->argv) == 1) {
                    $this->help($cli);
                    return false;
                }
                return $this->mergeIntoCommand($cli->parse($this->argv, false));
            default:
                $this->help();
                return false;
        }
    }
    
    protected function help(Cli $cli = null)
    {
        static::write_ln('PHP Bump version: version bumper and other git helpers');
        static::write_ln('(c) quazardous <berliozdavid@gmail.com>');
        static::write_ln();
        if ($cli) {
            $cli->writeHelp();
            return;
        }
        static::write_ln('USAGE: bump_version <command> [...]');
        static::write_ln('<command> can be:');
        static::write_ln('  bump: bump version');
        static::write_ln('  merge_into <branch>: merge current branch into given branch');
        static::write_ln();
    }
    
    protected function disclaimer()
    {
        static::write_ln();
        static::write_ln('# DICLAIMER: you should always copy/paste these commands one by one!');
        static::write_ln();
    }
    
    protected function bumbCommand(Args $args)
    {
        if ($args->getOpt('version')) {
            $version = $args->getOpt('version');
        } elseif (is_file($this->config['version_file'])) {
            $version = trim(file_get_contents($this->config['version_file']));
        } else {
            $version = '0.0.0';
        }
        
        $matches = null;
        if (preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/', $version, $matches)) {
            $versionObj = (object) [
                'major' => $matches[1],
                'minor' => $matches[2],
                'patch' => $matches[3],
            ];
        } else {
            throw new \RuntimeException("Something is wrong with the current version : $version", -1);
        }
        
        $bump = false;
        if ($args->getOpt('version')) {
            $bump = true;
        } elseif ($args->getOpt('major')) {
            ++$versionObj->major;
            $versionObj->minor = 0;
            $versionObj->patch = 0;
            $bump = true;
        } elseif ($args->getOpt('minor')) {
            ++$versionObj->minor;
            $versionObj->patch = 0;
            $bump = true;
        } elseif ($args->getOpt('patch')) {
            ++$versionObj->patch;
            $bump = true;
        } else {
            static::write_ln("Current version : $version");
        }
        
        if ($bump) {
            $nextVersion = sprintf('%d.%d.%d', $versionObj->major, $versionObj->minor, $versionObj->patch);
        
            // A bump should only be done on hotfix-* or release-* with a clean workspace
            $dirty = null;
            $branch = static::git_current_branch($dirty);
            static::write_ln("Current branch: $branch");
            
            if ($dirty) {
                static::write_ln("You have uncommitted work !");
                static::write($dirty);
                static::write_ln('---');
            }
            
            if (!static::test_branch_in($branch, $this->config['release_branches'])) {
                static::write_ln('You are not on a correct branch to release ('.implode(', ', array_values($this->config['release_branches'])).") !");
                if (!static::read_confirm("You should not bump version !")) {
                    throw new \RuntimeException("ABORTING", -1);
                }
            }
        
            if (!static::read_confirm("About to bump version from $version to $nextVersion ?")) {
                throw new \RuntimeException("ABORTING", -1);
            }
            static::write_ln("updating {$this->config['version_file']}...");
            if (false === static::file_put_contents($this->config['version_file'], $nextVersion."\n")) {
                throw new \RuntimeException("Cannot write version file " . $this->config['version_file'], -1);
            }
        }
        
        if ($bump) {
            $text = static::read_text('Enter a release description', '');
        }
        
        if (($bump || $args->getOpt('c')) && $this->config['define_version_file']) {
            $data = <<<EOT
<?php
// GENERATED by bump_version
// DO NOT EDIT
define('{$this->config['define_version_name']}', '$nextVersion');
        
EOT;
        
            static::write_ln("updating {$this->config['define_version_file']}...");
            if (false === static::file_put_contents($this->config['define_version_file'], $data)) {
                throw new \RuntimeException("Cannot write define version file " . $this->config['define_version_file'], -1);
            }
        }
        
        if ($bump) {
            if ($this->config['changelog_file']) {
                static::write_ln("updating {$this->config['changelog_file']}...");
                if (false === static::file_prepend_contents($this->config['changelog_file'], "$nextVersion: $text\n")) {
                    throw new \RuntimeException("Cannot write changelog file " . $this->config['changelog_file'], -1);
                }
            }
        
            $texts = [
                'commit' => $text ? "Version $nextVersion: $text" : '',
                'merge' => $text ? "Merge $branch: $text" : '',
                'tag' => $text ? "Release $nextVersion: $text" : '',
                'mergeback' => $text ? "Merge develop" : '',
            ];
        
            foreach ($texts as &$text) {
                if ($text) {
                    $text = "-m \"$text\"";
                }
            }
            static::write_ln();
            static::write_ln("# You should finish the release with :");
            static::write_ln("git add -A");
            static::write_ln("git commit -a {$texts['commit']}");
            if ($branch == $this->config['develop_branch']) {
                static::write_ln("# Keep the develop branch up-to-date :");
                static::write_ln("git pull");
                static::write_ln("git push");
            }
            if ($branch != $this->config['master_branch']) {
                static::write_ln();
                static::write_ln("# Merge on the master branch :");
                static::write_ln("git checkout master");
                static::write_ln("git pull");
                static::write_ln("git merge --no-ff $branch {$texts['merge']}");
            }
            static::write_ln("git tag -a $nextVersion {$texts['tag']}");
            static::write_ln("git push");
            static::write_ln();
            if ($branch == $this->config['develop_branch']) {
                static::write_ln("# Get back on the develop branch :");
            } else {
                static::write_ln("# Merge back all the new stuff on the develop branch :");
            }
            static::write_ln("git checkout develop");
            if ($branch != $this->config['develop_branch']) {
                static::write_ln();
                static::write_ln("git pull");
                static::write_ln("git merge --no-ff $branch {$texts['merge']}");
                static::write_ln("git push");
                if ($branch != $this->config['master_branch']) {
                    static::write_ln();
                    static::write_ln("# OPTIONAL:");
                    static::write_ln();
                    static::write_ln("# Go back on your branch :");
                    static::write_ln("git checkout $branch");
                    static::write_ln();
                    static::write_ln("# Or if the branch is a temporary branch you can delete it :");
                    static::write_ln("git branch -d $branch");
                }
            }
            static::write_ln();
            
            $this->disclaimer();
        }
    }
    
    protected function mergeIntoCommand(Args $args)
    {
        $target = $args->getArg('target');
        if (!static::git_branch_exists($target)) {
            throw new \RuntimeException("Target branch $target does not exist", -1);
        }
        $dirty = null;
        $branch = static::git_current_branch($dirty);
        static::write_ln("Current branch: $branch");
        
        if ($dirty) {
            static::write_ln("You have uncommitted work !");
            static::write($dirty);
            static::write_ln('---');
        }
        
        $text = static::read_text('Enter a merge description', '');
        
        $texts = [
            'commit' => $text,
            'merge' => $text ? "Merge $branch: $text" : '',
            'mergeback' => $text ? "Merge $target" : '',
        ];
        
        foreach ($texts as &$text) {
            $text = "-m \"$text\"";
        }
        
        static::write_ln();
        if ($dirty) {
            static::write_ln("# You should commit your work :");
            static::write_ln("git add -A");
            static::write_ln("git commit -a {$texts['commit']}");
        }
        
        if ($args->getOpt('paranoid')) {
            if ($target == $this->config['develop_branch'] || $target == $this->config['master_branch']) {
                static::write_ln("# We will pull and merge $target before all");
                static::write_ln("git checkout $target");
                static::write_ln("git pull");
                static::write_ln("git checkout $branch");
                static::write_ln("git merge $target {$texts['mergeback']}");
            }
        }
        
        static::write_ln("# Checkout the target branch $target :");
        static::write_ln("git checkout $target");
        if ($target == $this->config['develop_branch'] || $target == $this->config['master_branch']) {
            static::write_ln("# You may want to pull $target :");
            static::write_ln("git pull");
        }
        
        static::write_ln("# Merge $branch into $target :");
        static::write_ln("git merge $branch {$texts['merge']}");
        if ($target == $this->config['develop_branch'] || $target == $this->config['master_branch']) {
            static::write_ln("# You may want to push $target :");
            static::write_ln("git push");
        }
        
        static::write_ln("# Go back to your working branch $branch :");
        static::write_ln("git checkout $branch");
        if ($target == $this->config['develop_branch'] || $target == $this->config['master_branch']) {
            static::write_ln("# At some point you may want to keep your working branch up-to-date :");
            static::write_ln("git merge $target {$texts['mergeback']}");
        }
        
        static::write_ln();
        if ($target == $this->config['master_branch']) {
            static::write_ln("# WARNING: merging to $target should be done with caution.");
            static::write_ln("# You should use the bump command.");
            static::write_ln();
        }
        
        $this->disclaimer();
    }
    
    protected function init()
    {
        $this->check();
        if (!$this->config()) {
            throw new \RuntimeException("You must configure " . static::CONFIG_FILE, -1);
        }
        
    }
    
    protected function check()
    {
        if (!is_dir('.git')) {
            throw new \RuntimeException("You must be at the top level of a git repo", -1);
        }

        return true;
    }
    
    protected $config;
    
    protected function config()
    {
        if (!is_file(static::CONFIG_FILE))
        {
            static::write_ln("Config file not found (" . static::CONFIG_FILE . ")");
            if (!static::read_confirm("Create config file?")) {
                return false;
            }
            
            $config = [];
            if (! $config['master_branch'] = static::read_text("Branch master", 'master')) {
                return false;
            }
            if (! $config['develop_branch'] = static::read_text("Branch develop", 'develop')) {
                return false;
            }
            if (! $config['version_file'] = static::read_text("Version file", '/VERSION')) {
                return false;
            }
            
            $data = <<<EOT
<?php
// GENERATED by bump_version
return [
    'master_branch' => '{$config['master_branch']}',
    'develop_branch' => '{$config['develop_branch']}',
    'version_file' => __DIR__ . '{$config['version_file']}',
    // 'define_version_file' => __DIR__ . '/app/version.php', // you can add a 'PHP accessible' define with the current version
    // 'define_version_name' => 'APP_VERSION', // the define name
    // 'changelog_file' => __DIR__ . '/CHANGELOG', // A basic CHANGELOG
    'release_branches' => ['hotfix' => 'hotfix-*', 'release' => 'release-*'], // The branches you should release on
];

EOT;
            if (false === static::file_put_contents(static::CONFIG_FILE, $data)) {
                throw new \RuntimeException("Cannot write config file " . static::CONFIG_FILE, -1);
            }
            
        }
        
        static::write_ln("Loading config from " . static::CONFIG_FILE);
        $this->config = include './' . static::CONFIG_FILE;
        
        $this->config += [
            'master_branch' => 'master',
            'develop_branch' => 'develop',
            'version_file' => 'VERSION',
            'release_branches' => ['hotfix' => 'hotfix-*', 'release' => 'release-*'],
            'define_version_file' => null,
            'define_version_name' => null,
            'changelog_file' => null,
        ];
        
        return true;
    }
    
    public static function error($string, $code = 1)
    {
        static::write_ln('ERROR: ' . $string . ' (' . $code . ')');
        die($code);
    }
    
    public static function write($string)
    {
        echo $string;
    }
    
    public static function write_ln($string = '')
    {
        static::write($string . "\n");
    }
    
    public static function read_confirm($question, $confirm = 'yes')
    {
        static::write($question." ['$confirm' to continue]: ");
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        $res = trim(strtolower($line));
        fclose($handle);
    
        return $res == $confirm;
    }
    
    public static function read_text($question, $default = null)
    {
        static::write($question." [".(is_null($default) ? '*null*' : $default)."]: ");
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        $res = trim($line);
        if (!$res) {
            $res = $default;
        }
        fclose($handle);
    
        return $res;
    }
    
    public function git_branch_exists($branch)
    {
        $res = shell_exec(sprintf('git rev-parse --verify %s 2>&1; echo $?', $branch));
        $lines = preg_split('/$\R?^/m', $res);
        return array_pop($lines) == 0;
    }
    
    public static function git_current_branch(&$dirty = null)
    {
        $res = shell_exec('git status -sb');
        $lines = preg_split('/$\R?^/m', $res);
        if (count($lines) > 1) {            
            $dirty = $res;
        } else {
            $dirty = false;
        }
        
        $matches = null;
        if (preg_match('/^## (.+)\.\.\..+$/', $lines[0], $matches)) {
            $branch = $matches[1];
        } elseif (preg_match('/^## (.+)$/', $lines[0], $matches)) {
            $branch = $matches[1];
        } else {
            throw new \RuntimeException("Cannot determine current branch !");
        }
        
        return $branch;
    }
    
    public static function test_branch_in($branch, array $branches)
    {
        $ok = false;
        foreach ($branches as $b) {
            $p = str_replace('*', '.*', $b);
            $p = "/^$p$/";
            if (preg_match($p, $branch)) {
                $ok = true;
            }
        }
        return $ok;
    }
    
    public static function file_prepend_contents($filename, $string)
    {
        if (!is_dir(dirname($filename))) {
            return false;
        }
        if (!is_file($filename)) {
            file_put_contents($filename, '');
        }
        $context = stream_context_create();
        $fp = fopen($filename, 'r', 1, $context);
        $tmpname = md5($string);
        file_put_contents($tmpname, $string);
        file_put_contents($tmpname, $fp, FILE_APPEND);
        fclose($fp);
        unlink($filename);
        rename($tmpname, $filename);
    }
    
    public static function file_put_contents($filename, $data)
    {
        if (!is_dir(dirname($filename))) {
            return false;
        }
        return file_put_contents($filename, $data);
    }
    
}