<?php

/**
 * Deployer
 * By Sam-Mauris Yong
 * 
 * Released open source under New BSD 3-Clause License.
 * Copyright (c) Sam-Mauris Yong <sam@mauris.sg>
 * All rights reserved.
 */

namespace Deployer;

use Deployer\Payload\Payload;
use Symfony\Component\Process\Process;
use Packfire\Logger\File as Logger;

/** 
 * The deployer generic class that helps to pull Git repositories
 *
 * @author Sam-Mauris Yong / mauris@hotmail.sg
 * @copyright Copyright (c) Sam-Mauris Yong
 * @license http://www.opensource.org/licenses/bsd-license New BSD License
 * @package Deployer
 * @since 1.0.0
 */
abstract class Deployer
{
    
    /**
     * The keyword for deploying the commit
     */
    const HOOK_DEPLOY_KEY = '[deploy]';
    
    /**
     * The keyword for skipping this commit
     */
    const HOOK_SKIP_KEY = '[skipdeploy]';
    
    protected $options = array(
        
        /**
         * Set whether to use HTTPS or not. If authentication is used,
         * this is overwritten to be true.
         */
        'https' => true,
        
        /**
         * The target directory to deploy
         */
        'target' => __DIR__,
        
        /**
         * Determine whether deploys will be automated.
         * if true, only commits with [skipdeploy] will be skipped, and the rest will be deployed.
         * if false, only commits with [deploy] will be deployed, and the rest will be skipped.
         */
        'autoDeploy' => true,
        
        /**
         * The log date time format
         * See http://www.php.net/manual/en/function.date.php for formatting syntax.
         */
        'dateFormat' => 'Y-m-j H:i:sO',
        
        /**
         * The deployment log file
         */
        'logFile' => 'deploy.log',
        
        /**
         * The default branch to fetch
         */
        'branch' => 'master',
        
        /**
         * An array of IPs that is valid for the deployment operation
         */
        'ipFilter' => null,
        
    );
    
    /**
     * The username for HTTPS authentication
     * @var string
     * @since 1.0.0
     */
    protected $username;
    
    /**
     * The username for HTTPS authentication
     * @var string
     * @since 1.0.0
     */
    protected $password;
    
    /**
     * The payload
     * @var \Deployer\Payload\Payload
     * @since 1.0.1
     */
    protected $payload;

    /**
     * The logger that writes
     * @var \Psr\Log\LoggerInterface
     * @since 1.1.3
     */
    protected $logger;
    
    /**
     * Create a new Deployer object
     * @since 1.0.0
     */
    public function __construct(Payload $payload, $options = null)
    {
        set_error_handler(array($this, 'errorHandler'));

        if (is_array($options)) {
            $this->options($options);
        }
        
        // if it is not an absolute path then we use the current working directory
        if (!preg_match('/^(?:\/|\\|[a-z]\:\\\).*$/i', $this->options['logFile'])) {
            $this->options['logFile'] = getcwd() . '/' . $this->options['logFile'];
        }

        $this->logger = new Logger($this->options['logFile']);
        $this->payload = $payload;
    }

    public function errorHandler($errno, $errstr, $errfile = null, $errline = null, $errcontext = null)
    {
        throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
    }
    
    /**
     * Update the options in Deployer
     * @param array $options
     * @since 1.0.0
     */
    public function options($options)
    {
        foreach ($this->options as $key => &$value) {
            if (array_key_exists($key, $options)) {
                $value = $options[$key];
            }
        }
    }
    
    /**
     * Enter the credentials for authentication
     * @param string $username The username
     * @param string $password The password
     * @since 1.0.0
     */
    public function login($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->options['https'] = true;
        $this->logger->info(sprintf('Signing in as "%s".', $username));
    }

    /**
     * Execute a shell command and perform logging
     * @param string $cmd The command to execute
     * @since 1.0.0
     */
    public function execute($cmd)
    {
        $this->logger->info(sprintf('Executing command: %s', $cmd));
        $process = new Process($cmd);
        if ($process->run() == 0) {
            $output = $process->getOutput();
        } else {
            throw new \RuntimeException('Failed to run command "' . $cmd . '". Output: ' . $process->getOutput());
        }
        if ($output) {
            $this->logger->info(sprintf("Output:\n%s", $output));
        }
    }
    
    /**
     * Recursively destroy a directory
     * @param string $dir The directory to destroy
     * @return boolean Tells if successful or not.
     * @since 1.0.0
     */
    protected static function destroyDir($dir)
    {
        if (!file_exists($dir)) {
            return false;
        }
        if (!is_dir($dir) || is_link($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            self::destroyDir($dir . DIRECTORY_SEPARATOR . $file);
        }
        return rmdir($dir);
    }
    
    /**
     * Recursively change the permissions of files and folders
     * @param string $dir The path to set new permissions
     * @param integer $mode The permissions to set
     * @since 1.0.0
     */
    protected static function chmodR($dir, $mode)
    {
        if (!file_exists($dir)) {
            return;
        }
        if (is_dir($dir) && !is_link($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                self::chmodR($dir . DIRECTORY_SEPARATOR . $file, $mode);
            }
        }
        chmod($dir, $mode);
    }
    
    /**
     * Performs IP filtering check
     * @throws Exception Thrown when requestor IP is not valid
     * @since 1.0.0
     */
    protected function ipFilter()
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        if ($this->options['ipFilter'] && !in_array($ipAddress, (array)$this->options['ipFilter'])) {
            throw new \Exception('Client IP not in valid range.');
        }
        $this->logger->info('IP Address ' . $ipAddress . ' filtered.');
    }
    
    /**
     * Build the URL to clone the git repository
     * @return string The URL returned
     * @since 1.0.0
     */
    abstract public function buildUrl();
    
    /**
     * Find the next commit to deploy based on the rules of [deploy] and [skipdeploy]
     * @return string Returns the commit to clone
     * @since 1.0.0
     */
    protected function findCommit()
    {
        $node = null;
        $commits = array_reverse($this->payload->commits());
        if ($this->options['autoDeploy']) {
            foreach ($commits as $commit) {
                /* @var $commit \Deployer\Payload\Commit */
                if (strpos($commit->message(), self::HOOK_SKIP_KEY) === false) {
                    $node = $commit->commit();
                    break;
                }
                $this->logger->info('Skipping node "' . $commit->commit() . '".');
            }
        } else {
            foreach ($commits as $commit) {
                if (strpos($commit->message(), self::HOOK_DEPLOY_KEY) !== false) {
                    $node = $commit->commit();
                    break;
                }
                $this->logger->info('Skipping node "' . $commit->commit() . '".');
            }
        }
        return $node;
    }
    
    /**
     * Perform the deployment operations
     * @since 1.0.0
     */
    public function deploy()
    {
        $url = $this->buildUrl();
        $node = $this->findCommit();
        
        if ($url && $node) {
            $this->logger->info(sprintf('Commit "%s" will be checked out.', $node));
            $path = $this->options['target'];
            
            $currentDir = getcwd();
            
            ignore_user_abort(true);
            set_time_limit(0);
            
            if (!is_dir($path)) {
                $this->logger->info('Target directory not found, creating directory at ' . $path);
                mkdir($path);
            }
            chdir($path);
            try {
                $this->execute('git rev-parse');
            } catch (\Exception $e) {
                $this->logger->info('Repository not found. Cloning repository.');
                $this->execute('git init');
                $this->execute(sprintf('git remote add origin "%s"', $url));
            }
            $this->logger->info(sprintf('Checking out repository at %s', $node));
            $this->execute(sprintf('git pull origin %s', $this->options['branch']));
            $this->execute(sprintf('git checkout %s', $node));
            
            chdir($currentDir);
        } else {
            $this->logger->info('No node found to deploy.');
        }
        $this->logger->info('Deploy completed.');
    }
}
