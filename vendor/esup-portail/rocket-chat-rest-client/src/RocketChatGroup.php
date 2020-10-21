<?php

namespace RocketChat;

use Httpful\Request;
use RocketChat\Client;

class Group extends Client {

	public $id;
	public $name;
	public $members = array();
	public $archived = false;
	public $readonly = true;
	public $announcement = "";

	public function __construct($name, $members = array(), $options = array(), $instanceurl = null, $restroot = null){
		if(!is_null($instanceurl) && !is_null($restroot)){
			parent::__construct($instanceurl, $restroot);
		}else {
			parent::__construct();
		}
		if( is_string($name) ) {
			$this->name = $name;
		} else if( isset($name->_id) ) {
			$this->name = $name->name;
			$this->id = $name->_id;
		}
		if( isset($options['readonly'])){
			$this->readonly = (bool) $options['readonly'];
		}
		if( isset($options['archived'])){
			$this->archived = (bool) $options['archived'];
		}
		foreach($members as $member){
			if( is_a($member, '\RocketChat\User') ) {
				$this->members[] = $member;
			} else if( is_string($member) ) {
				// TODO
				$this->members[] = new User($member);
			}
		}
	}

	/**
	* Creates a new private group.
	*/
	public function create($verbose=false){
		// get user ids for members
		$members_id = array();
		foreach($this->members as $member) {
			if( is_string($member) ) {
				$members_id[] = $member;
			} else if( isset($member->username) && is_string($member->username) ) {
				$members_id[] = $member->username;
			}
		}

		$response = Request::post( $this->api . 'groups.create' )
			->body(array('name' => $this->name, 'members' => $members_id, 'archived' => $this->archived, 'readonly' => $this->readonly))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->id = $response->body->group->_id;
			return $response->body->group;
		} else {
			if($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			}
			return false;
		}
	}

	/**
	* Retrieves the information about the private group, only if you’re part of the group.
	*/
	public function info($verbose=false) {
		if (isset($this->id )){
			// If the id is defined, we use it
			$response = Request::get( $this->api . 'groups.info?roomId=' . $this->id )->send();
		} else {
			// If the id is not defined, we use the name
			$response = Request::get( $this->api . 'groups.info?roomName=' . $this->name )->send();
		}

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->id = $response->body->group->_id;
			if (isset($response->body->group->archived) && $response->body->group->archived == true) {
				$this->archived = true;
			} else {
				$this->archived = false;
			}
			if (isset($response->body->group->announcement)) {
				$this->announcement = $response->body->group->announcement;
			} else {
				$this->announcement = "";
			}
			return $response->body;
		} else {
			if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			}
			return false;
		}
	}

	/**
	* Post a message in this group, as the logged-in user
	*/
	public function postMessage( $text ) {
		$message = is_string($text) ? array( 'text' => $text ) : $text;
		if( !isset($message['attachments']) ){
			$message['attachments'] = array();
		}

		$response = Request::post( $this->api . 'chat.postMessage' )
			->body( array_merge(array('channel' => '#'.$this->name), $message) )
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			return false;
		}
	}

	/**
	* Set the announcement of this group, as the logged-in user
	*/
	public function setAnnouncement( $text, $verbose = false ) {
		$message = is_string($text) ? array( 'announcement' => $text ) : $text;

		$response = Request::post( $this->api . 'groups.setAnnouncement' )
			->body( array_merge(array('roomId' => $this->id), $message) )
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->announcement = $text;
			return true;
		} else {
			if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			}
			return false;
		}
	}


	/**
	* Removes the private group from the user’s list of groups, only if you’re part of the group.
	*/
	public function close(){
		$response = Request::post( $this->api . 'groups.close' )
			->body(array('roomId' => $this->id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			return false;
		}
	}

	/**
	* Removes the private group from the user’s list of groups and set it as read-only, only if you’re part of the group.
	*/
	public function archive(){
		$response = Request::post( $this->api . 'groups.archive' )
			->body(array('roomId' => $this->id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->archived = true;
			return true;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			return false;
		}
	}

	/**
	* Set group as writable and visible to members, only if you’re part of the group.
	*/
	public function unarchive(){
		$response = Request::post( $this->api . 'groups.unarchive' )
			->body(array('roomId' => $this->id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->archived = false;
			return true;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			return false;
		}
	}

	/**
	* Deletes the private group.
	*/
	public function delete(){
		$response = Request::post( $this->api . 'groups.delete' )
			->body(array('roomId' => $this->id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			return false;
		}
	}

	/**
	* Removes a user from the private group.
	*/
	public function kick( $user , $verbose = false ){
		// get group and user ids
		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.kick' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			}
			return false;
		}
	}

	/**
	 * Adds user to the private group.
	 */
	public function invite( $user, $verbose = false ) {

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.invite' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			if ($verbose) {
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			}
			return false;
		}
	}

	/**
	 * Adds owner to the private group.
	 */
	public function addOwner( $user ) {

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.addOwner' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			return false;
		}
	}

	/**
	 * Removes owner of the private group.
	 */
	public function removeOwner( $user ) {

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.removeOwner' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group ".__FUNCTION__." error".$message . "\n" );
			return false;
		}
	}

	/**
	 * Adds moderator to the private group.
	 */
	public function addModerator( $user , $verbose = false) {

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.addModerator' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			if ($verbose) {
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group addModerator error".$message . "\n" );
			}
			return false;
		}
	}

	/**
	 * Removes moderator of the private group.
	 */
	public function removeModerator( $user , $verbose = false) {

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.removeModerator' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			if ($verbose) {
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group removemoderator error".$message . "\n" );
			}
			return false;
		}
	}

	/**
	* Lists the users or participants of a private group.
	*/
	public function members($verbose=false){
		$response = Request::get( $this->api . 'groups.members?roomId=' . $this->id )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$members = array();
			foreach($response->body->members as $member){
				$user = new User($member->username, null, get_object_vars($member), $this->instanceurl, $this->restroot);
				$user->info();
				$members[] = $user;
			}
			return $members;
		} else {
			if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group members error".$message . "\n" );
			}
			return false;
		}
	}

    public function moderators($verbose=false){
        $response = Request::get( $this->api . 'groups.moderators?roomId=' . $this->id )->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $moderators = array();
            foreach($response->body->moderators as $moderator){
                $user = new User($moderator->username, null, get_object_vars($moderator), $this->instanceurl, $this->restroot);
                $user->info();
                $moderators[] = $user;
            }
            return $moderators;
        } else {
            if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Can't list moderators of this group. Error : ".$message . "\n" );
            }
            return false;
        }
    }

	/**
	* Create a link to invite users to this group
	* 	$days :	The number of days that the invite will be valid for.
	*		$maxUses : The number of times that the invite can be used.
	*/
	public function getInviteLink($days=0, $maxUses=0){

		$response = Request::post( $this->api . 'findOrCreateInvite' )
			->body(array('rid' => $this->id, 'days' => $days, 'maxUses' => $maxUses ))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $response->body->url;
		} else {
            $message = isset($response->body->error) ? $response->body->error : $response->body->message;
            $this->logger->error( "Group getInviteLink error".$message . "\n" );
			return false;
		}
	}

	public function getMessages($verbose=false){
        $response = Request::get( $this->api . 'groups.messages?roomId=' . $this->id )->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $messages = array();
            foreach($response->body->messages as $message){
                $messages[$message->_id] = $message;
            }
            return $messages;
        } else {
            if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group getMessages error ".$message . "\n" );
            }
            return false;
        }
    }

    public function cleanHistory($verbose=false, $oldest='1970-01-01', $latest='now'){
        $oldest = new \DateTime($oldest);
        $latest = new \DateTime($latest);
        $format = 'Y-m-d\TH:i:s.u\Z';

        $response = Request::post( $this->api . 'rooms.cleanHistory')
        ->body(array('roomId' => $this->id, 'oldest' => $oldest->format($format), 'latest' => $latest->format($format)))->send();
        if( $response->code != 200 || !isset($response->body->success) || $response->body->success != true ) {
            if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Delete all messages ".$message . "\n" );
            }
        }
    }

    public function isGroupAlreadyExists($verbose=false){
        if(isset($this->id)) {
            $response = Request::get( $this->api . 'rooms.adminRooms?filter=' . $this->id )->send();
        } else {
            $response = Request::get( $this->api . 'rooms.adminRooms?filter=' . $this->name )->send();
        }


        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            foreach($response->body->rooms as $room){
                if(isset($this->id)){
                    return true; // RoomId is unique
                } else {
                    // Need to check that roomName is exactly the same.
                    if($this->name == $room->name) {
                        return true;
                    }
                }
            }
        } else {
            if ($verbose){
                $message = isset($response->body->error) ? $response->body->error : $response->body->message;
                $this->logger->error( "Group isGroupAlreadyExists error ".$message . "\n" );
            }
            return false;
        }
    }
}
