<?php
//////////////////////////////
// The Hosting Tool
// Free - THT Type
// By Jonny H
// Released under the GNU-GPL
//////////////////////////////

//Check if called by script
if(THT != 1){die();}

//Create the class
class p2h {

	public $acpForm = array(), $orderForm = array(), $acpNav = array(), $acpSubNav = array(); # The HTML Forms arrays
	public $signup = true; # Does this type have a signup function?
	public $cron = true; # Do we have a cron?
	public $acpBox = true; # Want to show a box thing?
	public $clientBox = true; # Show a box in client cp?
        public $name = "Post2Host"; # Human readable name of the package.

	private $con; # Forum SQL

	# Start the functions #

	public function __construct() { # Assign stuff to variables on creation
		global $main, $db;
		$this->acpForm[] = array("Signup Posts", '<input name="signup" type="text" id="signup" size="5" onkeypress="return onlyNumbers();" />', 'signup');
		$this->acpForm[] = array("Monthly Posts", '<input name="monthly" type="text" id="monthly" size="5" onkeypress="return onlyNumbers();" />', 'monthly');
		$this->orderForm[] = array("Forum Username", '<input name="type_fuser" type="text" id="type_fuser" />', 'fuser');
		$this->orderForm[] = array("Forum Password", '<input name="type_fpass" type="password" id="type_fpass" />', 'fpass');
		$query = $db->query("SELECT * FROM `<PRE>config` WHERE `name` LIKE 'p2hforum;:;%'");
		while($data = $db->fetch_array($query)) {
			$content = explode(";:;", $data['name']);
			if($fname != $content[2]) {
				$values[] = array($content[2], $content[2]);
			}
			$fname = $content[2];
		}
		$this->acpForm[] = array("Forum", $main->dropDown("forum", $values), 'forum');
		$this->acpNav[] = array("P2H Forums", "forums", "lightning.png", "P2H Forums");
		$this->clientNav[] = array("Forum Posting", "forums", "lightning.png", "Forum Posting");
	}

	public function acpPage() {
		global $main, $style, $db;
		switch($main->getvar['do']) {
			default:
				if($_POST) {
					foreach($main->postvar as $key => $value) {
						if($value == "" && !$n) {
							if($key != "prefix") {
							$main->errors("Please fill in all the fields!");
							$n++;
							}
						}
					}
					if(!$n) {
						$forumcon = @mysql_connect($main->postvar['hostname'], $main->postvar['username'], $main->postvar['password'], true);
						if(!$forumcon) {
							$main->errors("mySQL Details incorrect!");
						}
						else {
							$select = @mysql_select_db($main->postvar['database'], $forumcon);
							if(!$select) {
								$main->errors("Couldn't select database!");
							}
							else {
								$query = $this->queryForums($main->postvar['name']);
								if($db->num_rows($query) != 0) {
									$main->errors("Forum name exists!");
								}
								else {
									$db->query("INSERT INTO `<PRE>config` (name, value) VALUES('p2hforum;:;username;:;{$main->postvar['name']}', '{$main->postvar['username']}')");
									$db->query("INSERT INTO `<PRE>config` (name, value) VALUES('p2hforum;:;password;:;{$main->postvar['name']}', '{$main->postvar['password']}')");
									$db->query("INSERT INTO `<PRE>config` (name, value) VALUES('p2hforum;:;database;:;{$main->postvar['name']}', '{$main->postvar['database']}')");
									$db->query("INSERT INTO `<PRE>config` (name, value) VALUES('p2hforum;:;hostname;:;{$main->postvar['name']}', '{$main->postvar['hostname']}')");
									$db->query("INSERT INTO `<PRE>config` (name, value) VALUES('p2hforum;:;prefix;:;{$main->postvar['name']}', '{$main->postvar['prefix']}')");
									$db->query("INSERT INTO `<PRE>config` (name, value) VALUES('p2hforum;:;type;:;{$main->postvar['name']}', '{$main->postvar['forum']}')");
									$main->errors("Forum Added!");
								}
							}
						}
					}
				}
				$array['CONTENT'] = $style->replaceVar("tpl/addforum.tpl");
				break;

			case "edit":
				$query = $this->queryForums();
				if($db->num_rows($query) == 0) {
					$array['CONTENT'] = "There are no forums to delete!";
				}
				else {
					if($main->getvar['name']) {
						if($_POST) {
							foreach($main->postvar as $key => $value) {
								if($value == "" && !$n && $key != "password") {
									$main->errors("Please fill in all the fields!");
									$n++;
								}
							}
							if(!$n) {
								$db->query("UPDATE `<PRE>config` SET `value` = '{$main->postvar['username']}' WHERE `name` = 'p2hforum;:;username;:;{$main->getvar['name']}'");
								$db->query("UPDATE `<PRE>config` SET `value` = '{$main->postvar['database']}' WHERE `name` = 'p2hforum;:;database;:;{$main->getvar['name']}'");
								$db->query("UPDATE `<PRE>config` SET `value` = '{$main->postvar['hostname']}' WHERE `name` = 'p2hforum;:;hostname;:;{$main->getvar['name']}'");
								$db->query("UPDATE `<PRE>config` SET `value` = '{$main->postvar['prefix']}' WHERE `name` = 'p2hforum;:;prefix;:;{$main->getvar['name']}'");
								if($main->postvar['password']) {
									$db->query("UPDATE `<PRE>config` SET `value` = '{$main->postvar['password']}' WHERE `name` = 'p2hforum;:;password;:;{$main->getvar['name']}'");
								}
								$main->errors("Forum Edited!");
							}
						}
						$forumdata = $this->forumData($main->getvar['name']);
						$array2['USER'] = $forumdata['username'];
						$array2['DB'] = $forumdata['database'];
						$array2['HOST'] = $forumdata['hostname'];
						$array2['NAME'] = $main->getvar['name'];
						$array2['PREFIX'] = $forumdata['prefix'];
						$array['CONTENT'] = $style->replaceVar("tpl/editforum.tpl", $array2);
					}
					else {
						$array['CONTENT'] .= "<ERRORS>";
						while($data = $db->fetch_array($query)) {
							$content = explode(";:;", $data['name']);
							if($fname != $content[2]) {
								$array['CONTENT'] .= $main->sub("<strong>".$content[2]."</strong>", '<a href="?page=type&type=p2h&sub=forums&do=edit&name='.$content[2].'"><img src="'. URL .'themes/icons/pencil.png"></a>');
							}
							$fname = $content[2];
						}
					}
				}
				break;

			case "delete":
				$query = $this->queryForums();
				if($db->num_rows($query) == 0) {
					$array['CONTENT'] = "There are no forums to delete!";
				}
				else {
					if($main->getvar['name']) {
						$db->query("DELETE FROM `<PRE>config` WHERE `name` LIKE 'p2hforum;:;%;:;{$main->getvar['name']}'");
						$main->errors("Forum deleted!");
					}
					$array['CONTENT'] .= "<ERRORS>";
					while($data = $db->fetch_array($query)) {
						$content = explode(";:;", $data['name']);
						if($fname != $content[2]) {
							$array['CONTENT'] .= $main->sub("<strong>".$content[2]."</strong>", '<a href="?page=type&type=p2h&sub=forums&do=delete&name='.$content[2].'"><img src="'. URL .'themes/icons/delete.png"></a>');
						}
						$fname = $content[2];
					}
				}
				break;
		}
		echo $style->replaceVar("tpl/manageforums.tpl", $array);
	}

	public function signup() {
		global $db, $main, $type;
		$fuser = $main->getvar['type_fuser'];
		$forum = $this->determineForum($main->getvar['package']);
		$this->con = $this->forumCon($forum);
		$details = $this->forumData($forum);
		$select = $db->query("SELECT * FROM `<PRE>user_packs`");
		while($data = $db->fetch_array($select)) {
			$pdetails = $type->userAdditional($data['id']);
			if($pdetails['fuser'] == $fuser) {
				$n++;
			}
		}
		if(!$n) {
			switch($this->checkSignup($details['type'], $details['prefix'])) {
				case 1:
					//What we do here?
					$main->getvar['type_fpass'] = 0;
					break;

				case 0:
					return "You haven't got enough posts for this package!<br />The required amount is: ". $this->getSignup($main->getvar['package']);
					break;

				case 3:
					return "Forum username is incorrect!";
					break;

				case 4:
					return "Forum password is incorrect!";
					break;
			}
		}
		else {
			return "That forum username is already used!";
		}
	}

	public function cron() {
		global $db, $main, $type, $server, $email;
		$query = $db->query("SELECT * FROM `<PRE>user_packs`");
		$checkdate = explode(":", $db->config("p2hcheck"));
		while($data = $db->fetch_array($query)) {
			$ptype = $type->determineType($data['pid']);
			if($ptype == "p2h") {
				$fuser = $type->userAdditional($data['id']);
				$forum = $this->determineForum($data['pid']);
				$fdetails = $this->forumData($forum);
				$this->con = $this->forumCon($forum);
				$posts = $this->checkMonthly($fdetails['type'], $fuser['fuser'], $fdetails['prefix']);
				$mposts = $this->getMonthly($data['pid']);
				if($checkdate[0] < date("m")) {
					if(date("d") == date("t")) {
						if($posts < $mposts) {
							$user = $db->client($data['userid']);
							$query2 = $db->query("SELECT * FROM `<PRE>user_packs` WHERE `userid` = '{$data['id']}'");
							$data2 = $db->fetch_array($query2);
							$sd1 = strftime("%m", $data2['signup']);
							$sd2 = strftime("%Y", $data2['signup']);
							$sdate = "$sd1$sd2";
							$cd1 = date("m");
							$cd2 = date("Y");							
							$chkdate = "$cd1$cd2";
							if($sdate != $chkdate) {
								$server->suspend($data['id'], "Only posted $posts out of $mposts!");
								echo "<b>". $user['user'] ." (".$fuser['fuser'].")</b>: Suspended for not posting monthly amount! ($posts out of $mposts)<br />";
							}
							$dbcheck = 1;
						}
					}
					if(date("d") == "20" && $checkdate[1] != "1") {
						if($posts < $mposts) {
							$user = $db->client($data['userid']);
							$query2 = $db->query("SELECT * FROM `<PRE>user_packs` WHERE `userid` = '{$data['id']}'");
							$data2 = $db->fetch_array($query2);
							$sd1 = strftime("%m", $data2['signup']);
							$sd2 = strftime("%Y", $data2['signup']);
							$sdate = "$sd1$sd2";
							$cd1 = date("m");
							$cd2 = date("Y");							
							$chkdate = "$cd1$cd2";
							if($sdate != $chkdate) {
								$emaildata = $db->emailTemplate("p2hwarning");
								$array['USERPOSTS'] = $posts;
								$array['MONTHLY'] = $mposts;
								$email->send($user['email'], $emaildata['subject'], $emaildata['content'], $array);
								echo "<b>". $user['user'] ." (".$fuser['fuser'].")</b>: Has been warned about monthly posting!<br />";
							}
							$dbcheck = 2;
						}
					}
				}
			}
		}
		if($dbcheck == 1) {
			if(date("m") == 12) {
				$checkmonth = "0";
			}
			else {
				$checkmonth = date("m");
			}
			$db->updateConfig("p2hcheck", $checkmonth);
		}
		elseif($dbcheck == 2) {
			$db->updateConfig("p2hcheck", $checkdate[0].":1");
		}
	}

	public function acpBox() {
		global $main, $db, $type;
		$box[0] = "Forum Posting:<br />";
		$user = $main->getvar['do'];
		$query = $db->query("SELECT * FROM `<PRE>user_packs` WHERE `userid` = '{$user}'");
		$data = $db->fetch_array($query);
		$forum = $this->determineForum($data['pid']);
		$user = $type->userAdditional($data['id']);
		$fdetails = $this->forumData($forum);
		$this->con = $this->forumCon($forum);
		$posts = $this->checkMonthly($fdetails['type'], $user['fuser'], $fdetails['prefix']);
		$box[1] = $posts ." (". $this->getMonthly($data['pid']) ." Needed)<br />Forum Username: ". $user['fuser'];
		return $box;
	}

	public function clientBox() {
		global $main, $db, $type;
		$box[0] = "Forum Posting:<br />";
		$user = $_SESSION['cuser'];
		$query = $db->query("SELECT * FROM `<PRE>user_packs` WHERE `userid` = '{$user}'");
		$data = $db->fetch_array($query);
		$forum = $this->determineForum($data['pid']);
		$user = $type->userAdditional($data['id']);
		$fdetails = $this->forumData($forum);
		$this->con = $this->forumCon($forum);
		$posts = $this->checkMonthly($fdetails['type'], $user['fuser'], $fdetails['prefix']);
		$box[1] = $posts ." (". $this->getMonthly($data['pid']) ." Needed)<br />Forum Username: ". $user['fuser'];
		return $box;
	}

	public function clientPage() {
		global $main, $db, $type, $style;
		$user = $_SESSION['cuser'];
		$query = $db->query("SELECT * FROM `<PRE>user_packs` WHERE `userid` = '{$user}'");
		$data = $db->fetch_array($query);
		$forum = $this->determineForum($data['pid']);
		$user = $type->userAdditional($data['id']);
		$fdetails = $this->forumData($forum);
		$this->con = $this->forumCon($forum);
		$posts = $this->checkMonthly($fdetails['type'], $user['fuser'], $fdetails['prefix']);
		$monthly = $this->getMonthly($data['pid']);
		$array['USER'] = $user['fuser'];
		if($posts >= $monthly) {
			$array['MESSAGE'] = "<strong>Well Done!</strong><br />You have achieved the monthly posts of ". $monthly .". You're total posts is ". $posts .".";
		}
		else {
			$need = $monthly - $posts;
			$array['MESSAGE'] = "<strong>You Need More Posts!</strong><br />You haven't achieved the monthly posts of ". $monthly .". You're total posts is ". $posts .". You need ". $need ." more posts!";
		}
		echo $style->replaceVar("tpl/forumposting.tpl", $array);
	}

	private function forumCon($name) { # Returns a forum sql
		global $main;
		$data = $this->forumData($name);
		$forumcon = @mysql_connect($data['hostname'], $data['username'], $data['password'], true);
		if(!$forumcon) {
			$array['Error'] = "Forum SQL Details incorrect!";
			$array['Forum Name'] = $name;
			$main->error($array);
		}
		else {
			$select = @mysql_select_db($data['database'], $forumcon);
			if(!$select) {
				$array['Error'] = "Couldn't select forum database!";
				$array['Forum Name'] = $name;
				$main->error($array);
			}
			else {
				return $forumcon;
			}
		}
	}

	private function getSignup($id) { # Returns the signup posts for a package
		global $type;
		$data = $type->additional($id);
		return $data['signup'];
	}

	private function getMonthly($id) { # Returns the signup posts for a package
		global $type;
		$data = $type->additional($id);
		return $data['monthly'];
	}

	private function checkMonthly($forum, $fuser, $prefix) {
		$nmonth = date("m");
		$nyear = date("y");

		switch($forum) {
			case "ipb":
				$n = 0;
				$forumuser = $fuser;
				$select = mysql_query("SELECT * FROM {$prefix}members WHERE `name` = '{$forumuser}'", $this->con);
				$display = mysql_fetch_array($select);
				$dname = $display['members_display_name'];

				//Get Posts
				$sposts = mysql_query("SELECT * FROM {$prefix}posts WHERE `author_name` = '{$dname}'", $this->con);

				//Count with time
				while($data2 = mysql_fetch_array($sposts)) {
					$date = explode(":", strftime("%m:%y" ,$data2['post_date']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;
                        case "ipb3":
				$n = 0;
				$forumuser = $fuser;
				$select = mysql_query("SELECT * FROM {$prefix}members WHERE `name` = '{$forumuser}'", $this->con);
				$display = mysql_fetch_array($select);
				$dname = $display['members_display_name'];

				//Get Posts
				$sposts = mysql_query("SELECT * FROM {$prefix}posts WHERE `author_name` = '{$dname}'", $this->con);

				//Count with time
				while($data2 = mysql_fetch_array($sposts)) {
					$date = explode(":", strftime("%m:%y" ,$data2['post_date']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;

			case "mybb":
				$n = 0;
				$forumuser = $fuser;
				$select = mysql_query("SELECT * FROM {$prefix}posts WHERE `username` = '{$forumuser}'", $this->con);

				while($data2 = mysql_fetch_array($select)) {
					$date = explode(":", strftime("%m:%y" ,$data2['dateline']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;

			case "phpbb":
				$n = 0;
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE username = '{$fuser}'", $this->con);
				$mem = mysql_fetch_array($result);
				$select = mysql_query("SELECT * FROM {$prefix}posts WHERE `poster_id` = '{$mem['user_id']}'", $this->con);

				while($data2 = mysql_fetch_array($select)) {
					$date = explode(":", strftime("%m:%y" ,$data2['post_time']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;

			case "phpbb2":
				$n = 0;
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE username = '{$fuser}'", $this->con);
				$mem = mysql_fetch_array($result);
				$select = mysql_query("SELECT * FROM {$prefix}posts WHERE `poster_id` = '{$mem['user_id']}'", $this->con);

				while($data2 = mysql_fetch_array($select)) {
					$date = explode(":", strftime("%m:%y" ,$data2['post_time']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;

			case "vb":
				$n = 0;
				$forumuser = $fuser;
				$select = mysql_query("SELECT * FROM {$prefix}post WHERE `username` = '{$forumuser}'", $this->con);

				while($data2 = mysql_fetch_array($select)) {
					$date = explode(":", strftime("%m:%y" ,$data2['dateline']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;

			case "smf":
				$n = 0;
				$forumuser = $fuser;
				$select = mysql_query("SELECT * FROM {$prefix}messages WHERE `posterName` = '{$forumuser}'", $this->con);

				while($data2 = mysql_fetch_array($select)) {
					$date = explode(":", strftime("%m:%y" ,$data2['posterTime']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;

			case "aef":
				$n = 0;
				$forumuser = $fuser;
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE username = '{$fuser}'", $this->con);
				$mem = mysql_fetch_array($result);
				$select = mysql_query("SELECT * FROM {$prefix}posts WHERE `poster_id` = '{$mem['id']}'", $this->con);

				while($data2 = mysql_fetch_array($select)) {
					$date = explode(":", strftime("%m:%y" ,$data2['ptime']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
				}
				break;
			
			case "drupal":
				$n = 0;
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE name = '{$fuser}' LIMIT 1", $this->con);
				$mem = mysql_fetch_assoc($result);
				$result = mysql_query("SELECT * FROM `{$prefix}node` WHERE `type` = 'forum' AND `uid` = {$mem["uid"]}", $this->con);
				while($node = mysql_fetch_assoc($result)) {
					$nodes[] = $node;
				}
				//Ack! Loops within loops! =P
				foreach($nodes as $key => $value) {
					$date = explode(":", strftime("%m:%y", $value['created']));
					if($nmonth <= $date[0] && $nyear <= $date[1]) {
						$n++;
					}
					
					$result = mysql_query("SELECT * FROM `{$prefix}comments` WHERE `nid` = {$value["nid"]} AND `uid` = {$mem["uid"]}", $this->con);
					//It messes up if I don't do this.
					unset($comments);
					//
					while($comment = mysql_fetch_assoc($result)) {
						$comments[] = $comment;
					}
					foreach($comments as $key2 => $value2) {
						$date = explode(":", strftime("%m:%y", $value2['timestamp']));
						if($nmonth <= $date[0] && $nyear <= $date[1]) {
							$n++;
						}
					}
				}
				
				
			break;
		}
		return $n;
	}

	private function checkSignup($forum, $prefix) {
		global $db, $main;

		$fuser = $main->getvar['type_fuser'];
		$fpass = $main->getvar['type_fpass'];
		$signup = $this->getSignup($main->getvar['package']);
		file_put_contents("log.log", $forum . "HELLO", FILE_APPEND);

		switch($forum) {
			case "ipb":
			// Look up member
			$result = mysql_query("SELECT * FROM `{$prefix}members` WHERE name = '{$fuser}'", $this->con);
			$member = $db->fetch_array($result);
			$memail = $member['email'];

			//Get Salt
			$select = mysql_query("SELECT * FROM `{$prefix}members_converge` WHERE `converge_email` = '{$memail}'", $this->con);
			$hash = mysql_fetch_array($select);

			if(md5(md5($hash['converge_pass_salt']) . md5($fpass)) == $hash['converge_pass_hash']) {
				if(mysql_num_rows($result) == "1") {
					//Check Posts
					if(stripslashes($signup) <= $member['posts']) {
						return 1;
					}
					//That shit below looks complicated doesn't it lol. I thought the same. To many brackets for my mind.
					else {
						return 0;
					}
				}
				else {
					return 3;
				}
			}
			else {
				return 4;
			}
			break;

                        case "ipb3":
			// Look up member
			$result = mysql_query("SELECT * FROM `{$prefix}members` WHERE name = '{$fuser}'", $this->con);
			$member = $db->fetch_array($result);
			$memail = $member['email'];

			//Get Salt
			//$select = mysql_query("SELECT * FROM `{$prefix}members_converge` WHERE `converge_email` = '{$memail}'", $this->con);
			$select = mysql_query("SELECT * FROM `{$prefix}members` WHERE `email` = '{$memail}'", $this->con);
			$hash = mysql_fetch_array($select);

			if(md5(md5($hash['members_pass_salt']) . md5($fpass)) == $hash['members_pass_hash']) {
				if(mysql_num_rows($result) == "1") {
					//Check Posts
					if(stripslashes($signup) <= $member['posts']) {
						return 1;
					}
					//That shit below looks complicated doesn't it lol. I thought the same. To many brackets for my mind.
					else {
						return 0;
					}
				}
				else {
					return 3;
				}
			}
			else {
				return 4;
			}
			break;

			case "mybb":
				// Look up member
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE username = '{$fuser}'", $this->con);
				if($db->num_rows($result) == "0") {
					return 3;
				}
				else {
					$member = mysql_fetch_array($result);
					if(md5(md5($member['salt']) . md5($fpass)) == $member['password']) {
						if($member['postnum'] >= $signup) {
							return 1;
						}
						else {
							return 0;
						}
					}
					else {
						return 4;
					}
				}
				break;

			case "phpbb":
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE username = '{$fuser}'", $this->con);
				if(mysql_num_rows($result) == "0") {
					return 3;
				}
				else {
					$member = mysql_fetch_array($result);
					if(phpbb_check_hash($fpass, $member['user_password'])) {
						$posts = mysql_query("SELECT * FROM `{$prefix}posts` WHERE poster_id = '{$member['user_id']}'", $this->con);
						$mposts = stripslashes(mysql_num_rows($posts));
						if($mposts >= $signup) {
							return 1;
						}
						else {
							return 0;
						}
					}
					else {
						return 4;
					}
				}
				break;

			case "phpbb2":
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE username = '{$fuser}'", $this->con);
				if(mysql_num_rows($result) == "0") {
					return 3;
				}
				else {
					$member = mysql_fetch_array($result);
					if(md5($fpass) == $member['user_password']) {
						if($member['user_posts'] >= $signup) {
							return 1;
						}
						else {
							return 0;
						}
					}
					else {
						return 4;
					}
				}
				break;

			case "vb":
				$result = mysql_query("SELECT * FROM `{$prefix}user` WHERE username = '{$fuser}'", $this->con);
				if(mysql_num_rows($result) == "0") {
					return 3;
				}
				else {
					$member = mysql_fetch_array($result);
					if(md5(md5($fpass) . $member['salt']) == $member['password']) {
						if($member['posts'] >= $signup) {
							return 1;
						}
						else {
							return 0;
						}
					}
					else {
						return 4;
					}
				}
				break;

			case "smf":
				$result = mysql_query("SELECT * FROM `{$prefix}members` WHERE memberName = '{$fuser}'", $this->con);
				if(mysql_num_rows($result) == "0") {
					return 3;
				}
				else {
					$member = mysql_fetch_array($result);
					if(sha1(strtolower($member['memberName']) . $fpass) == $member['passwd']) {
						if($member['posts'] >= $signup) {
							return 1;
						}
						else {
							return 0;
						}
					}
					else {
						return 4;
					}
				}
				break;

			case "aef":
				$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE username = '{$fuser}'", $this->con);
				if(mysql_num_rows($result) == "0") {
					return 3;
				}
				else {
					$member = mysql_fetch_array($result);
					if(md5($member['salt'] . $fpass) == $member['password']) {
						if($member['posts'] >= $signup) {
							return 1;
						}
						else {
							return 0;
						}
					}
					else {
						return array('true' => '0', 'customerror' => '<h1>Error</h1>That forum password is incorrect!');
					}
				}
				break;
				
				case "drupal":
					$result = mysql_query("SELECT * FROM `{$prefix}users` WHERE name = '{$fuser}' LIMIT 1", $this->con);
					if(mysql_num_rows($result) == 0) {
						return 3;
					}
					else {
						$member = mysql_fetch_array($result);
						if(md5($fpass) == $member['pass']) {
							$uid = $member['uid'];
							$drupalPosts = 0;
							$result = mysql_query("SELECT * FROM `{$prefix}node` WHERE `type` = 'forum' AND `uid` = {$uid}", $this->con);
							$drupalPosts = $drupalPosts + mysql_num_rows($result);
							while($threadsArray = mysql_fetch_assoc($result)) {
								$stuff[] = $threadsArray;
							}
							
							foreach($stuff as $key => $value) {
								$result = mysql_query("SELECT * FROM `{$prefix}comments` WHERE `nid` = {$value["nid"]} AND `uid` = {$uid}", $this->con);
								$drupalPosts = $drupalPosts + mysql_num_rows($result);
							}
							if($drupalPosts >= $signup) {
								return 1;
							}
							else {
								return 0;
							}
						}
						else {
							return 4;
						}
					}
				break;
		}
	}

	private function queryForums($name = 0) { # Returns the query for the forums in config table
		global $db;
		if($name) {
			return $db->query("SELECT * FROM `<PRE>config` WHERE `name` LIKE 'p2hforum;:;%;:;{$name}' ORDER BY `name` DESC");
		}
		else {
			return $db->query("SELECT * FROM `<PRE>config` WHERE `name` LIKE 'p2hforum;:;%'");
		}
	}

	private function forumData($name) { # Returns all the data for a forum
		global $db, $main;
		$query = $this->queryForums($name);
		if($db->num_rows($query) == 0) {
			$array['Error'] = "That forum doesn't exist!";
			$array['Forum Name'] = $name;
		}
		else {
			while($data = $db->fetch_array($query)) {
				$content = explode(";:;", $data['name']);
				$forumData[$content[1]] = $data['value'];
			}
			return $forumData;
		}
	}

	private function determineForum($id) { # Returns forum name with PID
		global $db;
		global $main, $type;
		$query = $db->query("SELECT * FROM `<PRE>packages` WHERE `id` = '{$db->strip($id)}'");
		if($db->num_rows($query) == 0) {
			$array['Error'] = "That package doesn't exist!";
			$array['User ID'] = $id;
			$main->error($array);
			return;
		}
		else {
			$data = $type->additional($id);
			return $data['forum'];
		}
	}
}
//End Type

///////////////////////////////////////////
// phpBB Password functions - All written by the phpBB team. All credit to them.
// Why don't you use a salt? ha mo f***ers
///////////////////////////////////////////

function phpbb_check_hash($password, $hash)
{
	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	if (strlen($hash) == 34)
	{
		return (_hash_crypt_private($password, $hash, $itoa64) === $hash) ? true : false;
	}

	return (md5($password) === $hash) ? true : false;
}

/**
* Encode hash
*/
function _hash_encode64($input, $count, &$itoa64)
{
	$output = '';
	$i = 0;

	do
	{
		$value = ord($input[$i++]);
		$output .= $itoa64[$value & 0x3f];

		if ($i < $count)
		{
			$value |= ord($input[$i]) << 8;
		}

		$output .= $itoa64[($value >> 6) & 0x3f];

		if ($i++ >= $count)
		{
			break;
		}

		if ($i < $count)
		{
			$value |= ord($input[$i]) << 16;
		}

		$output .= $itoa64[($value >> 12) & 0x3f];

		if ($i++ >= $count)
		{
			break;
		}

		$output .= $itoa64[($value >> 18) & 0x3f];
	}
	while ($i < $count);

	return $output;
}

/**
* The crypt function/replacement
*/
function _hash_crypt_private($password, $setting, &$itoa64)
{
	$output = '*';

	// Check for correct hash
	if (substr($setting, 0, 3) != '$H$')
	{
		return $output;
	}

	$count_log2 = strpos($itoa64, $setting[3]);

	if ($count_log2 < 7 || $count_log2 > 30)
	{
		return $output;
	}

	$count = 1 << $count_log2;
	$salt = substr($setting, 4, 8);

	if (strlen($salt) != 8)
	{
		return $output;
	}

	/**
	* We're kind of forced to use MD5 here since it's the only
	* cryptographic primitive available in all versions of PHP
	* currently in use.  To implement our own low-level crypto
	* in PHP would result in much worse performance and
	* consequently in lower iteration counts and hashes that are
	* quicker to crack (by non-PHP code).
	*/
	if (PHP_VERSION >= 5)
	{
		$hash = md5($salt . $password, true);
		do
		{
			$hash = md5($hash . $password, true);
		}
		while (--$count);
	}
	else
	{
		$hash = pack('H*', md5($salt . $password));
		do
		{
			$hash = pack('H*', md5($hash . $password));
		}
		while (--$count);
	}

	$output = substr($setting, 0, 12);
	$output .= _hash_encode64($hash, 16, $itoa64);

	return $output;
}
?>
