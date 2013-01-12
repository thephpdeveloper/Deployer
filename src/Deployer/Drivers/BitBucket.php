<?php

/**
 * Deployer
 * By Sam-Mauris Yong
 * 
 * Released open source under New BSD 3-Clause License.
 * Copyright (c) Sam-Mauris Yong <sam@mauris.sg>
 * All rights reserved.
 */

namespace Deployer\Drivers;
use Deployer\Deployer as Deployer;

/**
 * A deployer from pulling data from BitBucket
 *
 * @author Sam-Mauris Yong / mauris@hotmail.sg
 * @copyright Copyright (c) Sam-Mauris Yong
 * @license http://www.opensource.org/licenses/bsd-license New BSD License
 * @package Deployer\Drivers
 * @since 1.0.0
 */
class BitBucket extends Deployer {
    
    public function __construct($data, $options = null) {
        $this->options['ipFilter'] = array(
            '63.246.22.222'
        );
        parent::__construct($data, $options);
    }
    
    public function validate(){
        $this->log('Validation started');
        if(!$this->data){
            $this->validationError('Data Error: No data available.');
        }
        if($this->data['canon_url'] != 'https://bitbucket.org'){
            $this->validationError('Data Error: Canon URL is not BitBucket\'s');
        }
        if(!$this->data['commits']){
            $this->validationError('Data Error: No commits in push hook.');
        }
        if(!$this->data['repository'] || !$this->data['repository']['absolute_url']){
            $this->validationError('Data Error: Repository or absolute URL not set.');
        }
        if(!$this->data['repository']['owner'] || !$this->data['repository']['name'] || !$this->data['repository']['slug']){
            $this->validationError('Data Error: Repository information incomplete; missing owner, name or slug.');
        }
        $this->log('Validation successful');
    }
    
    public function buildUrl(){
        if($this->options['https']){
            $url = 'https://';
            if($this->username){
                $url .= $this->username;
                if($this->password){
                    $url .= ':' . $this->password;
                }
                $url .= '@';
            }
        }else{
            $url = 'http://';
        }
        $url .= 'bitbucket.org/' . $this->data['repository']['owner'] . '/' . $this->data['repository']['slug'] . '.git';
        return $url;
    }
    
    protected function findCommit(){
        $node = null;
        $commits = array_reverse($this->data['commits']);
        if($this->options['autoDeploy']){
            foreach($commits as $commit){
                if(strpos($commit['message'], self::HOOK_SKIP_KEY) === false){
                    $node = $commit['raw_node'];
                    break;
                }
                $this->log('Skipping node "' . $commit['raw_node'] . '".');
            }
        }else{
            foreach($commits as $commit){
                if(strpos($commit['message'], self::HOOK_DEPLOY_KEY) !== false){
                    $node = $commit['raw_node'];
                    break;
                }
                $this->log('Skipping node "' . $commit['raw_node'] . '".');
            }
        }
        return $node;
    }
    
}