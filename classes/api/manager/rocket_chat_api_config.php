<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * rocket chat api config class
 *
 * @package     mod_rocketchat
 * @author Céline Pervès<cperves@unistra.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_rocketchat\api\manager;

class rocket_chat_api_config {
    private $instanceurl;
    private $restapiroot;
    private $apiuser;
    private $apipassword;

    /**
     * @return mixed
     */
    public function get_instanceurl() {
        return $this->instanceurl;
    }

    /**
     * @return mixed
     */
    public function get_restapiroot() {
        return $this->restapiroot;
    }

    /**
     * @return mixed
     */
    public function get_apiuser() {
        return $this-> apiuser;
    }

    /**
     * @return mixed
     */
    public function get_apipassword() {
        return $this-> apipassword;
    }

    public function __construct(){
        if(is_null($this->instanceurl)){
            $config = get_config('mod_rocketchat');
            if(empty($config->instanceurl)){
                print_error('RocketChat instance url is empty');
            }
            if(empty($config->restapiroot)){
                print_error('RocketChat rest api root is empty');
            }
            if(empty($config->apiuser)){
                print_error('RocketChat api password is empty');
            }
            if(empty($config->apiuser)){
                print_error('RocketChat api password is empty');
            }
            $this->instanceurl = $config->instanceurl;
            $this->restapiroot = $config->restapiroot;
            $this->apiuser = $config->apiuser;
            $this->apipassword = $config->apipassword;
        }
    }

}