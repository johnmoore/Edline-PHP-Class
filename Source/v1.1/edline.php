<?PHP
/*   PHP Edline Class
#    Copyright (C) 2010 John Moore
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU General Public License as published by
#    the Free Software Foundation, either version 3 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require("uccon.php");
class Edline extends ucCon {
	var $name, $schoolname, $classes, $reportcards;
	var $cache = array(array(), "");
	
	public function GetClassData($id) {
		return $this->classes[$id];
	}


	public function Login($user, $password) {
		parent::sendGET("https://www.edline.net", "/Index.page");
		$sessid = reset(explode(";", $this->cookie))."; ";
		parent::sendPOST("https://www.edline.net", "/post/Index.page", "TCNK=authenticationEntryComponent&submitEvent=1&guestLoginEvent=&enterClicked=true&bscf=1&bscv=".urlencode($this->cookie)."&targetEntid=&ajaxSupported=yes&screenName=".$user."&kclq=".$password, $this->cookie);
		parent::sendGET("https://www.edline.net", "/pages/Catholic_High_School", $this->cookie);
		if (strpos($this->response['data'], "Welcome to Catholic High School's Edline Homepage") > 0) {
			$this->InitializeFields($this->response['data']);
			$ret = Array("name" => $this->name, "classes" => $this->classes, "reportcards" => $this->reportcards);
			return $ret;
		} else {
			return false;
		}
	}
	
	private function InitializeFields($srcHome) {
		$this->name = implode("__", array_reverse(explode(" ", strtr(reset(explode("</span>", end(explode("<span class=\"edlHeaderControlsBtnText\">", $srcHome, 4)), 2)), array(" " => "", "\n" => "", "&nbsp;" => " ")))));
		$this->schoolname = str_replace("\n", "", reset(explode("</div>", end(explode("</span></div>", end(explode("<div id=\"edlHomePageDocBoxAreaBoxTitleInnerWrapper\"", $srcHome, 2)), 2)))));
		$this->classes = $this->GetClasses($srcHome);
		$this->reportcards = $this->GetReportCards();
	}
	
	public function GetReportCards() {
		$reportdata = $this->GetReportData(true);
		$reportcards = array();
		foreach ($reportdata as $report) {
			if ($report[1] == $this->schoolname) {
				$reportcards[] = array(2 => $report[2], 3 => $report[3]);
			}
		}
		return $reportcards;
	}

	public function GetClasses($srcHome) {
		$srcHome = reset(explode("<div type=\"item\" title=\"-\" id=\"Separator1\" action=\"\" sel=\"\" gho=\"\" ewrd=\"\"", end(explode("<div type=\"menu\" title=\"My Classes &amp; Shortcuts\" id=\"myShortcutsItem\" enabled=\"Y\" width=\"330\" gho=\"\">", $srcHome, 2))));
		foreach (explode("<div type=\"item\" ", $srcHome) as $item) {
			$classes[][0] = "";
			$classes[count($classes) - 1][1] = "";
			$classes[count($classes) - 1][2] = reset(explode("\" id=\"", end(explode("title=\"", $item))));
			$classes[count($classes) - 1][3] = reset(explode("')", end(explode("action=\"code:mcViewItm('", $item))));
		}
		$classes = array_splice($classes, 1); //index 0 is garbage
		for ($i = 0; $i < count($classes); $i++) {
			$page = $this->ServePage($classes[$i][3]);
			$classes[$i][0] = end(explode("/Classes/", reset(explode("\r\n", end(explode("Location: ", $this->response['header']))))));
		}
		return $classes;
	}

	public function Logout() {
		parent::sendPOST("https://www.edline.net", "/post/UserDocList.page", "invokeEvent=clickLogout&eventParms=TCNK%3DheaderComponent&sessionRenewalEnabled=yes&sessionRenewalIntervalSeconds=300&sessionRenewalMaxNumberOfRenewals=25&sessionIgnoreInitialActivitySeconds=90&sessionHardTimeoutSeconds=1200&ajaxRequestKeySuffix=0", $this->cookie);
		return (strpos($this->response['header'], "302 Moved Temporarily") > 0 ? true : false);
	}

	public function SetLoginCookie($cookie) {
		$this->cookie = $cookie;
		parent::sendGET("https://www.edline.net", "/pages/Catholic_High_School", $this->cookie);
		if (strpos($this->response['data'], "Welcome to Catholic High School's Edline Homepage") > 0) {
			$this->InitializeFields($this->response['data']);
			return $this->name;
		} else {
			return false;
		}
	}

	private function GetGradeFrame($id, $usecache=false, $AcceptSemester=false, $skip=0) {
		if ($usecache == false || $this->cache[0][$id] == "") {
			$classdata = $this->GetClassData($id);
			parent::sendGET("https://www.edline.net", "/pages/Catholic_High_School/Classes/".$classdata[0]."/".$this->GetLastReportName($id, true, $skip)."/".$this->name, $this->cookie);
			$url = reset(explode("\"", end(explode("<iframe name=\"docViewBody\" id=\"docViewBodyFrame\" src=\"https://www.edline.net", $this->response['data'], 2))));
			parent::sendGET("https://www.edline.net", reset(explode("\"", end(explode("<iframe name=\"docViewBody\" id=\"docViewBodyFrame\" src=\"https://www.edline.net", $this->response['data'], 2)))), $this->cookie);
			if (strpos($this->response['data'], "<meta http-equiv=\"refresh\" content=\"0; URL=/Index.page\">") <= 0) {
				if ($AcceptSemester == true || $this->is_semester_report($this->response['data']) == false) {
					$this->cache[0][$id] = $this->response['data'];
					return $this->response['data'];
				} else {
					return $this->GetGradeFrame($id, $usecache, $AcceptSemester, $skip + 1);
				}
			} else {
				return false;
			}
		} else {
			return $this->cache[0][$id];
		}	
	}

	public function GetGradeLetter($id) {
		$GradeFrame = $this->GetGradeFrame($id, true);
		if ($GradeFrame != false) {
			return substr(end(explode("Grade: ", $GradeFrame, 2)), 0, 1);
		} else {
			return "?";
		}
	}

	public function GetGradeAverage($id) {
		$GradeFrame = $this->GetGradeFrame($id, true);
		if ($GradeFrame != false) {
			return rtrim(substr(end(explode("Average: ", $GradeFrame, 2)), 0, 5));
		} else {
			return "??";
		}
	}

	public function GetGradeString($id, $delimiter=" ") {
		return $this->GetGradeAverage($id).$delimiter.$this->GetGradeLetter($id);
	}

	public function GetLastReportDate($id, $usecache=false) {
		$classdata = $this->GetClassData($id);
		$reportdata = $this->GetReportData(true);
		
		foreach ($reportdata as $report) {
			if ($report[1] == $classdata[2] && $date == "") {
				$date = $report[0];
			}
		}
		
		return $date;
	}
	
	public function GetLastReportName($id, $usecache=false, $skip=0) {
		$classdata = $this->GetClassData($id);
		$reportdata = $this->GetReportData(true);
		$skipped = 0;

		foreach ($reportdata as $report) {
			if ($report[1] == $classdata[2]) {
				if ($skipped >= $skip) {				
					$name = $report[2];
					$this->classes[$id][1] = str_replace("&amp;", "_", str_replace("/", "_", str_replace(" ", "_", $report[2])));
					break;
				} else {
					$skipped++;
				}
			}
		}

		return $name;	
	}

	public function GetReportData($usecache=false) {
		if ($usecache == false || $this->cache[1] == "") {
			parent::sendPOST("https://www.edline.net", "/post/GroupHome.page", "invokeEvent=viewUserDocList&eventParms=undefined&sessionRenewalEnabled=yes&sessionRenewalIntervalSeconds=300&sessionRenewalMaxNumberOfRenewals=25&sessionIgnoreInitialActivitySeconds=90&sessionHardTimeoutSeconds=1200&ajaxRequestKeySuffix=0", $this->cookie);
			parent::sendGET("https://www.edline.net", "/UserDocList.page", $this->cookie);
			$vusr = reset(explode("\"", end(explode("?vusr=", $this->response['data'], 2))));
			parent::sendGET("https://www.edline.net", "/UserDocList.page?vusr=".$vusr, $this->cookie);
			$data = $this->response['data'];
			$this->cache[1] = $data;
		} else {
			$data = $this->cache[1];
		}
		
		$a1 = explode("<form method=\"post\" name=\"userDocListTableForm\" action=\"/post/UserDocList.page\">", $data, 2);

		$a2 = explode("<tr>", $a1[1]);

		for ($i=1; $i<count($a2); $i++) {
			$a3 = explode("<td class=\"dbnav\"", $a2[$i]);
			for ($ii=1; $ii<count($a3); $ii++) {
				$a4 = explode("\n", $a3[$ii]);
				if ($ii == 1) {
					$reportdata[$i - 1][0] = str_replace(" ", "", $a4[1]);
				} elseif ($ii == 3) {
					$reportdata[$i - 1][1] = ltrim($a4[2]);
				} elseif ($ii == 4) {
					$reportdata[$i - 1][2] = str_replace("&amp;", "_", str_replace("/", "_", str_replace(" ", "_", ltrim($a4[2]))));
				} elseif ($ii == 2) {
					$reportdata[$i - 1][3] = reset(explode("');\">View", end(explode("<a href=\"javascript:rlViewItm('", $a4[1], 2))));
				}
			}
		}
		return $reportdata;
	}
	
	private function ServePage($Entid) {
		parent::sendPOST("https://www.edline.net", "/post/GroupHome.page", "invokeEvent=myClassesResourceView&eventParms=TCNK%3DheaderComponent%3BtargetResEntid%3D".$Entid."&sessionRenewalEnabled=yes&sessionRenewalIntervalSeconds=300&sessionRenewalMaxNumberOfRenewals=25&sessionIgnoreInitialActivitySeconds=90&sessionHardTimeoutSeconds=1200&ajaxRequestKeySuffix=0", $this->cookie);
		return $this->response['header'];
	}

	public function GetGradeTable($id, $AcceptSemester=false) {
		$data = $this->GetGradeFrame($id, true, $AcceptSemester);
		if ($data == false) {		
			return array();
		}
		$body = reset(explode("</pre>", end(explode("Score Information", $data, 2)), 2));
		$a1 = explode("\n", $body);
		for ($i=0; $i<count($a1); $i++) {
			$newentry = array();
			if ((strpos($a1[$i], "/", 3) > 0 && $AcceptSemester == false) || ((strpos($a1[$i], "SubTotal") > 0 || strpos($a1[$i], "FINAL") > 0) && $AcceptSemester == true)) {
				if (strpos($a1[$i], "/", 3) > 0) {
					$a2 = explode("/", $a1[$i]);
					if (!is_numeric(substr($a2[1], 0, 1))) {
						$a2 = explode("/", $a2[1]."/".$a2[2]."/".$a2[3]);
						$a2[0] = reset(explode("/", $a1[$i], 2))."/".$a2[0];
					}
					if (substr(substr($a2[0], -3), 0, 1) == " ") {
						if (count(explode("   ", trim(substr($a2[0], 0, strlen($a2[0]) - 2)))) > 1) {
							$newentry['name'] = trim(end(explode("   ", trim(substr($a2[0], 0, strlen($a2[0]) - 2)), 2)));
							$newentry['cat'] = trim(reset(explode("   ", trim(substr($a2[0], 0, strlen($a2[0]) - 2)), 2)));
						} else {
							$newentry['name'] = trim(substr($a2[0], 0, strlen($a2[0]) - 2));
						}
						$newentry['date'] = end(explode(" ", $a2[0]))."/".$a2[1]."/".reset(explode(" ", $a2[2]));
						$newentry['cat'] = (isset($entry[$i]['cat']) ? $entry[$i]['cat'] : reset(explode(" ", substr($a2[2], 3, strlen($a2[2]) - 3), 2)));
						$a3 = explode(" ", substr($a2[2], 3, strlen($a2[2]) - 3));
						for ($ii=0; $ii<count($a3); $ii++) {
							if ($this->is_grade($a3[$ii])) {
								$newentry['score'] = $a3[$ii];
								$newentry['max'] = ltrim(end(explode(" ", substr($a2[2], 3, strlen($a2[2]) - 3), $ii + 2)));
							}
						}
					}
					$entries[] = Array('name' => $newentry['name'], 'cat' => $newentry['cat'], 'date' => $newentry['date'], 'score' => $newentry['score'], 'max' => $newentry['max']);
				} else { //it's a semester/exam report, or "report card"
					$newentry = array();
					if (strpos($a1[$i], "SubTotal") > 0) {
						$newentry['cat'] = "SubTotal";
						$a2 = explode("SubTotal ", $a1[$i], 2);
					} else {
						$newentry['cat'] = "FINAL";
						$a2 = explode("FINAL", $a1[$i], 2);
					}
					if (strpos($a1[$i], "SubTotal") > 0) {
						$newentry['quarter'] = ltrim(rtrim(end(explode(" ", reset(explode("     ", ltrim(end($a2))))))));
					} else {
						$newentry['quarter'] = "";
					}
					if (strpos($a1[$i], "SubTotal") > 0) {
						$temp = explode(" ", reset(explode("     ", ltrim(end($a2)))));
						array_splice($temp, -1);
						if (is_numeric($newentry['quarter'])) {
							$newentry['name'] = ltrim(rtrim(implode(" ", $temp)));
						} else {
							$newentry['name'] = ltrim(rtrim(implode(" ", $temp)))." ".$newentry['quarter'];
							$newentry['quarter'] = "";
						}
					} else {
						$newentry['name'] = reset(explode("  ", ltrim(end($a2)), 2));
					}
					if (is_numeric($newentry['name']) || str_replace(" ", "", $newentry['name']) == "") {
						$newentry['name'] = "     ";
					}
					$a3 = explode($newentry['name'], end($a2), 2);
					$a4 = explode("    ", end($a3), 2);
					$a5 = explode(" ", ltrim(end($a4)), 2);
					$newentry['weight'] = ltrim(rtrim($a5[0]));
					$a6 = explode(" ", ltrim(end($a5)), 2);
					$newentry['score'] = ltrim(rtrim($a6[0]));
					$a7 = explode(" ", ltrim(end($a6)), 2);
					$newentry['max'] = ltrim(rtrim($a7[0]));
					if ($newentry['name'] == "     ") {
						$newentry['name'] = "";
					}
					$entries[] = Array('cat' => $newentry['cat'], 'name' => $newentry['name'], 'quarter' => $newentry['quarter'], 'weight' => $newentry['weight'], 'score' => $newentry['score'], 'max' => $newentry['max']);
				}
			}
		}
		for ($i = 0; $i < count($entries); $i++) {
			if (!isset($entry[$i]['date'])) {
				continue;
			}
			$parts = explode("/", $entries[$i]['date'], 3);
			if (!checkdate($parts[0], $parts[1], substr(date("Y"), 0, -2).$parts[2])) {
				$date = $entries[$i]['type'];
				$entries[$i]['type'] = $entries[$i]['date'];
				$entries[$i]['date'] = $date;
			}
		}
		return $entries;
	}

	private function is_grade($input) {
		if (is_numeric($input)) {
			return true;
		} elseif (strpos($input, "*") > 0) {
			return true;
		} else {
			return false;
		}
	}

	private function is_semester_report($srcReport) {
		if (strpos(reset(explode("Average:", $srcReport)), "Semester") > 0) {
			return true;
		} else {
			return false;
		}
	}
}
?>