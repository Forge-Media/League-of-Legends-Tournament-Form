<?php
error_reporting(0);

// Required field names
$required = array('summonerName', 'tsNick', 'firstName', 'surname', 'email', 'terms');

// Loop over field names, make sure each one exists and is not empty

$error = false;

foreach($required as $field) {
  if (empty($_POST[$field])) {
    $error = false;
  } else{
	$error = true;
  }
}

if ($error) {
	$terms = $_POST['terms'];
	//Checks validation code
	if ($terms == "accepted"){
		$validDGL = dgl_validate($_POST['dgl']);
		if($validDGL == "valid"){
			
			submit_form();
		}
		else{
		
			$arrResult = array ('response'=>'error','reason'=>'Invalid DGL validation Code');
			echo json_encode($arrResult);
		}
		
	} else {
		$arrResult = array ('response'=>'error','reason'=>'Please accept the terms and conditions');
		echo json_encode($arrResult);	
	}
	
} else {
	
	$arrResult = array ('response'=>'error','reason'=>'Please complete all the required fields.');	
	echo json_encode($arrResult);
}

//Submit Entry
function submit_form(){	

	//headers
	session_cache_limiter('nocache');
	header('Expires: ' . gmdate('r', 0));
	header('Content-type: application/json');

	//Mail & Page Settings
	$enablePHPMailer = false;
	$subject = "FGN 1v1 League of Legends Tournament";

	//Mail set
	if(isset($_POST['email'])) {

		//Mail Settings
		$to = $_POST['email'];
		$fromEmail = 'admin@forgegaming.net';
		$fromName = 'Forge Gaming Network';

		//Form Field Settings
		$summonerName = $_POST['summonerName'];
		$tsNick = $_POST['tsNick'];
		$firstName = $_POST['firstName'];
		$surname = $_POST['surname'];
		$email = $_POST['email'];
		$region = $_POST['region'];
		
		//Checks Summoner Name is Valid
		$summonerDivision = summonerCheck($summonerName, $region);
		
		//If valid divison will be returned
		if($summonerDivision != false){

			//Google Spreadsheet Sub-Function
			if(submitGoogle($summonerName, $tsNick, $firstName, $surname, $email, $summonerDivision) == true){
				
				//Email message
				$message = email_message($summonerName, $tsNick, $firstName, $surname, $email, $summonerDivision);
				
				// Simple Mail
				if(!$enablePHPMailer) {
					//Headers
					$headers = '';
					$headers .= 'From: ' . $fromName . ' <' . $fromEmail . '>' . "\r\n";
					$headers .= "Reply-To: " .  $fromEmail . "\r\n";
					$headers .= "MIME-Version: 1.0\r\n";
					$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

					if (mail($to, $subject, $message, $headers)){

						$arrResult = array ('response'=>'success');
					} else{

						$arrResult = array ('response'=>'success','reason'=>'However: Unable to send confirmation email!');
					}

				// PHP Mailer Library - Docs: https://github.com/PHPMailer/PHPMailer
				} else {

					include("php-mailer/PHPMailerAutoload.php");
					//PHP Mailer Settings
					$mail = new PHPMailer;
					$mail->IsSMTP();                                      // Set mailer to use SMTP
					$mail->SMTPDebug = 0;                                 // Debug Mode

					// Step 3 - If you don't receive the email, try to configure the parameters below:
					//$mail->Host = 'mail.yourserver.com';				  // Specify main and backup server
					//$mail->SMTPAuth = true;                             // Enable SMTP authentication
					//$mail->Username = 'username';             		  // SMTP username
					//$mail->Password = 'secret';                         // SMTP password
					//$mail->SMTPSecure = 'tls';                          // Enable encryption, 'ssl' also accepted

					$mail->From = $fromEmail;
					$mail->FromName = $fromName;
					$mail->AddAddress($to);								  // Add a recipient
					$mail->AddReplyTo($email, $name);
					$mail->IsHTML(true);                                  // Set email format to HTML
					$mail->CharSet = 'UTF-8';
					$mail->Subject = $subject;
					$mail->Body    = $message;

					if(!$mail->Send()) {
						//Entry is submitted though email was unsuccesful
						$arrResult = array ('response'=>'success','reason'=>'However: Unable to send confirmation email!');
					}

					//Entry is submitted & email was successful
					$arrResult = array ('response'=>'success');
				} //Mail - END
			
				//Mail Debug Echo
				//echo json_encode($arrResult); - Seems unnecessary	& creates repition

			//Google Sub-Function Debug
			} else {

				$arrResult = array ('response'=>'error','reason'=>'Looks like you are already entered');
			}//Google - END
	
		//Summoner Name Check Debug
		} else {

			$arrResult = array ('response'=>'error','reason'=>'Invalid summoner name was entered, check your spelling');	
		}//Summoner - END

		//Main Debug Echo
		echo json_encode($arrResult);
	
	//Main Debug
	} else {
		
		//God help us all (I've never seen this error actually come up)
		$arrResult = array ('response'=>'error','reason'=>'Ask an FGN admin you should not see this error');
		echo json_encode($arrResult);
	}
}//Main - END


//Checks that summoner name exists in region
function summonerCheck($summonerName,$summoner_region){

	//Requires Riot API PHP Library
	include("riot/php-riot-api.php");

	//Member regex
	$summonerName = str_replace(' ', '',strtolower ($summonerName));
	
	//Create new Riot API object
	$check = new riotapi($summoner_region);

	//Use 'try' so we can utilise exception caching
	try {
		//Get Summoner's Details
		$summoner_array = $check->getSummonerByName($summonerName);

		//Get Summoner's ID
		$summoner_id = $summoner_array[$summonerName]['id'];

	} catch(Exception $e) {

		return false;
	};

	try {
		//Get Summoner's Details
		$summoner_league_array = $check->getLeague($summoner_id, "entry");

		//Get Summoner's Ranked Tier
		$summoner_league = $summoner_league_array[$summoner_id][0/* thread id */]["tier"/* thread key */];
		
		//Return Summoner's League
		return $summoner_league;
	} catch(Exception $e) {
		
		//Return unranked if unranked
		$summoner_league = "Unranked";
		return $summoner_league;
	};
}

//Submits new row to Google Spreadsheet
function submitGoogle($summonerName, $tsNick, $firstName, $surname, $email, $summonerDivision){

	// Zend library include path
	set_include_path(get_include_path() . PATH_SEPARATOR ."$_SERVER[DOCUMENT_ROOT]/form/zendgdata/library");

	include_once("Google_Spreadsheet.php");

	//Settings
	$u = "shadowrider921009@gmail.com";
	$p = "wcuptofixwupvkjh";
	$ss = new Google_Spreadsheet($u,$p);
	$ss->useSpreadsheet("FGN1v1");
	// if not setting worksheet, "Sheet1" is assumed
	// $ss->useWorksheet("worksheetName");
	$time = date(DATE_RFC2822);

	$row = array
		(	"Timestamp" => $time,
			"SummonerName" => str_replace(' ', '',strtolower ($summonerName)),
			"Summoner Division" => $summonerDivision,
			"Teamspeak Nickname" => $tsNick,
			"First Name" => $firstName,
			"Surname" => $surname,
			"Email" => $email
		);
	
	//Regex summoner name	
	$summonerName = str_replace(' ', '',strtolower ($summonerName));

	//Search for existing summoner in spreadsheet
	$rows = $ss->getRows('summonername="'.$summonerName.'"');
	$rows = array_filter($rows);
	if (!empty($rows)) {
		return false;	
	} else if ($ss->addRow($row)) {
		return true;
	} else {
		return false;
	}
}

//DGL A2P Validation Code
function dgl_validate($code){
	if($code == "512868"){
		return "valid";	
	} else
	{
		return "invalid";			
	}
	
} 

//Email template
function email_message($summonerName, $tsNick, $firstName, $surname, $email, $summonerDivision)
{
	
	return "
					<style type=\"text/css\">
						body {
						  width: 100%;
						  background-color: #f8f8f8;
						  margin: 0;
						  padding: 0;
						  -webkit-font-smoothing: antialiased;
						}
					
						@media only screen and (max-width: 640px) {
						  body {
							width: auto !important;
						  }
					
						  table table {
							width: 92% !important;
						  }
					
						  .eraseForMobile {
							width: 0;
							display: none !important;
						  }
					
						  .mobileSpace {
							height: 30px !important;
						  }
					
						  .centerIcon {
							width: 42px !important;
						  }
					
						  .lines {
							width: 100% !important;
							height: 1px !important;
							background-color: #e1e1e1 !important;
						  }
					
						  .pad {
							height: 30px !important;
						  }
					
						  .Scale {
							width: 92% !important;
						  }
					
						  .unsubscribe {
							width: 100% !important;
							text-align: center !important;
							font-size: 14px !important;
							line-height: 26px !important;
						  }
						}
					
						@media only screen and (max-width: 479px) {
						  body {
							width: auto !important;
						  }
					
						  table table {
							width: 92% !important;
						  }
					
						  .eraseForMobile {
							width: 0;
							display: none !important;
						  }
					
						  .mobileSpace {
							height: 20px !important;
						  }
					
						  .centerIcon {
							width: 42px !important;
						  }
					
						  .lines {
							width: 100% !important;
							height: 1px !important;
							background-color: #e1e1e1 !important;
						  }
					
						  .pad {
							height: 30px !important;
						  }
					
						  .Scale {
							width: 92% !important;
						  }
					
						  .unsubscribe {
							width: 100% !important;
							text-align: center !important;
							font-size: 14px !important;
							line-height: 26px !important;
						  }
						}
					  </style>
					</head>
					
					<body leftmargin=\"0\" topmargin=\"0\" marginwidth=\"0\" marginheight=\"0\">
					<!-- Wrapper -->
					<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
					<tr>
					<td width=\"100%\" valign=\"top\" bgcolor=\"#f8f8f8\">
					<!-- Header Text -->
					<table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" bgcolor=\"#ffffff\" align=\"center\" class=\"Scale\" style=\"border-left: 1px solid #e1e1e1; border-right: 1px solid #e1e1e1;border-top:1px solid #e1e1e1;margin-top:50px;\">
					  <tr>
						<td width=\"460\" class=\"Scale\">
					
						  <table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
							<tr>
							  <td height=\"30\" class=\"pad\">
							  </td>
							</tr>
						  </table>
					
						  <!-- Header Text Interior -->
						  <table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"460\" style=\"font-size: 16px; color: #b8b9c1; font-weight: normal; text-align: center; font-family: Helvetica, Arial, sans-serif; line-height: 24px; vertical-align: top;\" class=\"fontSmaller\">
								<img src=\"http://www.forgegaming.net/wp-content/uploads/2014/12/forge-logo-2015-dark.png\" style=\"margin:0 0 20px 0; width: 180px\" width=\"180\">
							  </td>
							  <td width=\"40\"></td>
							</tr>
					
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"460\" style=\"font-size: 16px; color: #b8b9c1; font-weight: normal; text-align: center; font-family: Helvetica, Arial, sans-serif; line-height: 24px; vertical-align: top;\" class=\"fontSmaller vero-editable\">
								<span style=\"text-decoration: none; color: #2f2f36; font-weight: bold;font-size:28px;line-height:32px;\">You have been entered into the tournament.</span><br>
							  </td>
							  <td width=\"40\"></td>
							</tr>
					
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"460\" style=\"font-size: 16px; color: #b8b9c1; font-weight: normal; text-align: center; font-family: Helvetica, Arial, sans-serif; line-height: 24px; vertical-align: top;\" class=\"fontSmaller vero-editable\">
								<span style=\"text-decoration: none; color: #2f2f36; font-weight: bold;font-size:18px;line-height:32px;margin:0 0 20px 0;\">The following details have been recorded:</span><br>
					<br>
							  </td>
							  <td width=\"40\"></td>
							</tr>
					
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"460\" style=\"font-size: 16px; color: #a0a0a5; font-weight: normal; text-align: center; font-family: Helvetica, Arial, sans-serif; line-height: 24px; vertical-align: top;\" class=\"fontSmaller vero-editable\">
							   Summoner Name: ".$summonerName."<br/>Teamspeak Nickname: ".$tsNick."<br/>First Name: ". $firstName."<br/>Surname: ".$surname."<br/>Email Address: ".$email."<br/>Summoner Division: ".$summonerDivision."
							  <td width=\"40\"></td>
							</tr>
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"512\" height=\"30\" class=\"mobileSpace\"></td>
							  <td width=\"40\"></td>
							</tr>
						  </table>
					
						</td>
					  </tr>
					</table>
					<!-- End Header Text -->
					
					
					<table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" bgcolor=\"#ffffff\" align=\"center\" class=\"Scale\" style=\"border-left: 1px solid #e1e1e1; border-right: 1px solid #e1e1e1;\">
					  <tbody>
						<tr>
						  <td width=\"460\" class=\"Scale\">
					
							<!-- Paragraph Message Text -->
							<table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
							  <tbody>
								<tr>
								  <td width=\"40\"></td>
								  <td width=\"510\" style=\"font-size: 14px; color: #a0a0a5; font-weight: normal; text-align: center; font-family: Helvetica, Arial, sans-serif; line-height: 24px; vertical-align: top;\" class=\"fontSmaller vero-editable\">
					
									<div>
									</div>
								  </td>
								  <td width=\"40\"></td>
								</tr>
								<tr>
								  <td width=\"40\"></td>
								  <td width=\"512\" height=\"10\" class=\"mobileSpace\"></td>
								  <td width=\"40\"></td>
								</tr>
							  </tbody>
							</table>
						  </td>
						</tr>
					  </tbody>
					</table>
					
					
					
					
					
					<!-- Start 2nd 2 Columns -->
					<table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" bgcolor=\"#ffffff\" align=\"center\" style=\"border-left: 1px solid #e1e1e1; border-right: 1px solid #e1e1e1;\">
					  <tr>
						<td width=\"460\">
					
						  <!-- Space -->
						  <table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
							<tr>
							  <td width=\"460\" height=\"10\">
							  </td>
							</tr>
						  </table>
						  <!-- End Space -->
					
						</td>
					  </tr>
					</table>
					<!-- End 2nd 2 Columns -->
					
					<!-- Outer  Text -->
					<table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" bgcolor=\"#f9f9f9\" align=\"center\" class=\"Scale\" style=\"border-left: 1px solid #e1e1e1; border-right: 1px solid #e1e1e1;border-top:1px solid #e1e1e1;\">
					  <tr>
						<td width=\"460\" class=\"Scale\">
					
						  <table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
							<tr>
							  <td height=\"10\" class=\"pad\">
							  </td>
							</tr>
						  </table>
					
						  <table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"460\" style=\"font-size: 16px; color: #b8b9c1; font-weight: normal; text-align: center; font-family: Helvetica, Arial, sans-serif; line-height: 24px; vertical-align: top;\" class=\"fontSmaller\">
					
							  </td>
							  <td width=\"40\"></td>
							</tr>
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"460\" style=\"font-size: 14px; color: #959599; font-weight: normal; font-family: Helvetica, Arial, sans-serif; line-height: 20px;text-align:center;\" class=\"vero-editable\">
								<p class=\"centerForMobile\">Thanks for entering. If this  was sent in error, please contact
								  <a href=\"admin@forgegaming.net\" style=\"text-decoration: none; color: #008f9b;font-weight:bold;\">admin@forgegaming.net</a>
								</p>
							  </td>
							  <td width=\"40\"></td>
							</tr>
							<tr>
							  <td width=\"40\"></td>
							  <td width=\"512\" height=\"10\" class=\"mobileSpace\"></td>
							  <td width=\"40\"></td>
							</tr>
						  </table>
					
						</td>
					  </tr>
					</table>
					<!-- End Outer  Text -->
					
					<!-- Space -->
					<table width=\"462\" bgcolor=\"#f0f0f0\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" style=\"border-left: 1px solid #e1e1e1; border-right: 1px solid #e1e1e1;border-top:1px solid #e1e1e1;\">
					  <tr>
						<td width=\"462\" height=\"10\">
						</td>
					  </tr>
					</table>
					<!-- End Space -->
					
					
					
					<!-- Shadow -->
					<table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\" class=\"Scale\">
					  <tr>
						<td width=\"460\" style=\"border-left: 1px solid #e1e1e1; border-right: 1px solid #e1e1e1; border-bottom: 1px solid #e1e1e1; border-radius: 0 0 10px 10px; background: #f0f0f0; \" class=\"Scale\">
					
						  <!-- Space -->
						  <table width=\"460\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" align=\"center\">
							<tr>
							  <td height=\"14\">
							  </td>
							</tr>
						  </table>
						  <!-- End Space -->
					
						</td>
					  </tr>
					</table>
					<!-- End Shadow -->
					
					<!-- Footer Logo -->
					
					<!-- End Wrapper -->
					</body>";
	
}

?>