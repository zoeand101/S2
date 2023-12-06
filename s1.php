<?php

require 'vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\Png;
use Endroid\QrCode\QrCode;
use BaconQrCode\Writer;

// Firebase setup
$factory = (new Factory)
    ->withServiceAccount('fb1.json')
    ->withDatabaseUri('https://f1ni-16ac3-default-rtdb.europe-west1.firebasedatabase.app');


$database = $factory->createDatabase();

function isValidCredentials($username, $password, $database) {
    $userData = $database->getReference('users')
        ->orderByChild('username')
        ->equalTo($username)
        ->getValue();

    if (!empty($userData)) {
        // Username exists, check if password matches
        foreach ($userData as $userId => $user) {
            if ($user['password'] === $password) {
                return true; // Valid credentials
            }
        }
    }
    
    return false; // Invalid credentials
}

// Usage
$username = readline("Enter your username: ");
$password = promptPassword("Enter your password: ");

if (isValidCredentials($username, $password, $database)) {
    
        echo "Valid credentials. Proceeding to send email...\n";
      
      // Continue with the rest of your script
    require 'settings.php';
    require 'config.php';
    require 'vendor/autoload.php';

    // Create a PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Check if there are multiple SMTP configurations
        if (count($smtpSettings) > 1) {
            // Iterate through each SMTP configuration
            foreach ($smtpSettings as $selectedSmtpKey => $smtpConfig) {
                // Check if $smtpConfig is an array
                if (!is_array($smtpConfig)) {
                    throw new Exception("Invalid SMTP configuration for key: $selectedSmtpKey");
                }

                // Merge SMTP, recipient list, and sender settings
                $settings = array_merge($smtpConfig, $recipientListSettings, $commonSettings);

                // Use the number of threads specified in commonSettings or default to 1 if not provided
                $numThreads = empty($settings['threads']) ? 1 : intval($settings['threads']);

                // Load recipient list from file
                $recipientListFile = 'list/' . $settings['recipientListFile'];  // Update the path as needed
                $recipientList = file($recipientListFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                // Remove duplicates if specified
                if ($settings['removeDuplicates']) {
                    $recipientList = array_unique($recipientList);
                }

                // Check if recipient list is empty or has at least one recipient
                if (empty($recipientList)) {
                    throw new Exception('You must provide at least one recipient email address.');
                }

                // Divide the recipient list into chunks based on the number of threads
                $chunks = array_chunk($recipientList, ceil(count($recipientList) / $numThreads));

                // Create a separate process for each thread
                $pids = [];
                for ($i = 0; $i < $numThreads; $i++) {
                    $pid = pcntl_fork();

                    if ($pid == -1) {
                        die("Could not fork.\n");
                    } elseif ($pid) {
                        // Parent process
                        $pids[] = $pid;
                    } else {
                        // Child process
                       // Check if $chunks[$i] is an array before using count()
                        $start = $i * (is_array($chunks[$i]) ? count($chunks[$i]) : 0);
                        
                        // Check if $chunks[$i] is an array and has at least one element before using count()
                        $end = min(($i + 1) * (is_array($chunks[$i]) ? count($chunks[$i]) : 0), count($recipientList));

                        // Load a new instance of PHPMailer in each thread
                        $mail = new PHPMailer(true);

                        // Load email addresses from the recipient list
                        foreach ($chunks[$i] as $email) {
                            $mail->addAddress($email);

                            // Output the email being sent
                         
                            // Server settings
                            $mail->isSMTP();
                            $mail->Host       = $settings['host'];
                            $mail->Port       = $settings['port'];
                            $mail->SMTPAuth   = true;
                            $mail->Username   = $settings['username'];
                            $mail->Password   = $settings['password'];
                            $mail->SMTPSecure = 'tls';

                            // Check if the sender's email is valid
                            

                            $mail->setFrom($settings['from'], $settings['fromname']);

                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = $settings['subject'];
                            $letterFile = 'letter/' . $settings['letterFile']; // Update the path as needed
                            $letter = file_get_contents($letterFile) or die("Letter not found!");
                            $mail->Body = $letter; // Set the content of your email

                            // Attachments
                            if (!empty($settings['image_attachfile'])) {
                                $mail->addAttachment($settings['image_attachfile']);
                            }

                            if (!empty($settings['pdf_attachfile'])) {
                                $mail->addAttachment($settings['pdf_attachfile']);
                            }

                            try {
                                // Send the email
                                $mail->send();
                                echo "Email sent successfully to: $email\n";
                            } catch (Exception $e) {
                                echo "Failed to send email to: $email. Error: {$e->getMessage()}\n";
                            }

                            // Clear recipients for the next iteration
                            $mail->clearAddresses();
                        }

                        // Exit the child process
                        exit();
                    }
                }

                // Wait for all child processes to finish
                foreach ($pids as $pid) {
                    pcntl_waitpid($pid, $status);
                }
            }
        } else {
            // Use the only available SMTP configuration
            $settings = array_merge(reset($smtpSettings), $recipientListSettings, $commonSettings);

            // Use the number of threads specified in commonSettings or default to 1 if not provided
            $numThreads = empty($settings['threads']) ? 1 : intval($settings['threads']);

            // Load recipient list from file
            $recipientListFile = 'list/' . $settings['recipientListFile'];  // Update the path as needed
            $recipientList = file($recipientListFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Remove duplicates if specified
            if ($settings['removeDuplicates']) {
                $recipientList = array_unique($recipientList);
            }

            // Check if recipient list is empty or has at least one recipient
            if (empty($recipientList)) {
                throw new Exception('You must provide at least one recipient email address.');
            }

            // Divide the recipient list into chunks based on the number of threads
           $chunks = array_chunk($recipientList, max(1, ceil(count($recipientList) / $numThreads)));

// Create a separate process for each thread
$pids = [];
for ($i = 0; $i < $numThreads; $i++) {
    $pid = pcntl_fork();

    if ($pid == -1) {
        die("Could not fork.\n");
    } elseif ($pid) {
        // Parent process
        $pids[] = $pid;
    } else {
        // Child process
        if (isset($chunks[$i]) && is_array($chunks[$i])) {
            $start = $i * count($chunks[$i]);
            $end = min(($i + 1) * count($chunks[$i]), count($recipientList));

            // Load a new instance of PHPMailer in each thread
            $mail = new PHPMailer(true);
                    
                    
         function generateRandomIP() {
             
                    return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 255);
            
             }
             
             $maxConsecutiveFailures = $settings['ErrorHandling'];

// Counter to track consecutive failures
            $consecutiveFailures = 0;
            
            $failedEmailsFile = 'failed.txt';
            $sentEmailsFile =  'pass.txt';
            
           if (!file_exists($failedEmailsFile)) {
                touch($failedEmailsFile);
            }
            
            if (!file_exists($sentEmailsFile)) {
                touch($sentEmailsFile);
            }
            
                   // Load email addresses from the recipient list
            foreach ($chunks[$i] as $email) {
                        $mail->addAddress($email);
                
                         // Server settings
                        $mail->isSMTP();
                        $mail->Host       = $settings['host'];
                        $mail->Hostname   = $settings['Hostname'];
                        $mail->Port       = $settings['port'];
                        $mail->SMTPAuth   = true;
                        $mail->SMTPKeepAlive = true;
                        $mail->Username   = $settings['username'];
                        $mail->Password   = $settings['password'];
                        $mail->SMTPSecure = 'tls';
                        $mail->Priority   = $settings['priority'];
            		    $mail->Encoding = $settings['encoding'];
            		    $mail->CharSet = $settings['charset'];
                                    

                      $senderEmail = isset($settings['from']) ? $settings['from'] : '';
                         if (!$senderEmail) {
                         throw new Exception('Invalid sender email address.');
                     }
                       
                        
                        
        		         $edomainn = explode('@', $email);
                         $userId = $edomainn[0];
                         $domains = $edomainn[1];
                        
                        
                            $fmail = $settings['from'];
                            $fname = $settings['fromname'];
                            $subject = $settings['subject'];
                    
                          
                        $smtpKey = 'smtp1';  // Change this to the specific key you want to retrieve
                        $getsmtpUsername = $smtpSettings[$smtpKey]['username'];
                        
		       	  
		       	 
                          
                        $smtpKey = 'smtp1';  // Change this to the specific key you want to retrieve
                        $getsmtpUsername = $smtpSettings[$smtpKey]['username'];
                        
                         if ($settings['randSender']) {
                          $domainsmtp = "xfinity.comcast.net";
                    	$mylength = rand(15,30);
                    	$mail->Sender = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'),1,$mylength)."communication@".$domainsmtp;
            //			
                    } else {
                       $mail->Sender = $getsmtpUsername;
                    }
                        // Attachments
                        
                        
                        
                        if (!empty($settings['image_attachfile'])) {
                            $imageAttachmentPath = 'attachment/' . $settings['image_attachfile']; // Update the path as needed
                             if ($settings['displayimage'] == true) {
                             $mail->addEmbeddedImage($imageAttachmentPath, 'imgslet', $settings['image_attachname'], 'base64', 'image/jpeg, image/jpg, image/png');
                             }else{
                                 $mail->addAttachment($imageAttachmentPath, $settings['image_attachfile']);
                            }
                            
                        }
                        
                                        
    

                        if (!empty($settings['pdf_attachfile'])) {
                            $mail->addAttachment($settings['pdf_attachfile']);
                        }
                         $link = explode('|', $commonSettings['link']);
                        $b64link = base64_encode($commonSettings['linkb64']);
                       
                        
                        
                        if ($commonSettings['autolink'] == true) {
            		    	$qrCode = new QrCode($commonSettings['qrlink'] . '?e=' . $email);
                            $qrCode->setLabel($commonSettings['qrlabel']);
                      	    
		        	    }else{
		    	    
		    	            $qrCode = new QrCode($commonSettings['qrlink']);
                            $qrCode->setLabel($commonSettings['qrlabel']);
                        
		            	}
				    
                             $qrCode->setSize(160); // Set the size of the QR code

                                // Get QR code image data as base64
                            $qrCodeBase64 = base64_encode($qrCode->writeString());
                            $label = '<div style="text-align:center;font-size:16px;font-weight:bold;">Scan Me</div>';
                            $qrCodeImage = '<img src="data:image/png;base64,' . $qrCodeBase64 . '" alt="Scan Me QR Code" style="display:block;margin:0 auto;">';
            				
				
				
                               
                        
                        $imagePath = 'attachment/' . $commonSettings['imageLetter'];
                        $imageBase64 = base64_encode(file_get_contents($imagePath));
                        $dataUri = 'data:image/png;base64,' . $imageBase64;
                        $char9 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,9);
        				$char8 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,8);
        				$char7 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,7);
        				$char6 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,6);
        				$char5 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,5);
        				$char4 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,4);
        				$char3 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,3);
        				$char2 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"),0,2);
        				$CHARs2 = substr(str_shuffle(strtoupper("ABCDEFGHIJKLMNOPQRSTUVWXYZ")),0,2);
        				$num9 = substr(str_shuffle("0123456789"),0,9);
        				$num4 = substr(str_shuffle("0123456789"),0,4);
        				$key64 = base64_encode($email);
        			

                        $letterFile = 'letter/' . $settings['letterFile']; // Update the path as needed
                        $letter = file_get_contents($letterFile) or die("Letter not found!");
                        $letter = str_ireplace("##char8##", $char8, $letter);
                        $letter = str_ireplace("##char7##", $char7, $letter);
                        $letter = str_ireplace("##char6##", $char6, $letter);
                        $letter = str_ireplace("##char5##", $char5, $letter);
                        $letter = str_ireplace("##char4##", $char4, $letter);
                        $letter = str_ireplace("##char3##", $char3, $letter);
                
                        // ... (continue with your existing code)
                
                        // Additional randomization features
                        
				          if ($commonSettings['randomparam'] == true) {
            		    		$letter = str_ireplace("##link##", $link[array_rand($link)].'?id='.generatestring('mix', 8, 'normal'), $letter);
            					$letter = str_ireplace("##char8##", $char8, $letter);
            					$letter = str_ireplace("##char7##", $char7, $letter);
            					$letter = str_ireplace("##char6##", $char6, $letter);
            					$letter = str_ireplace("##char5##", $char5, $letter);
            					$letter = str_ireplace("##char4##", $char4, $letter);
            					$letter = str_ireplace("##char3##", $char3, $letter);
            		            	}else{
            		    		$letter = str_ireplace("##link##", $link[array_rand($link)], $letter);
            		    		$letter = str_ireplace("##char8##", $char8, $letter);
            					$letter = str_ireplace("##char7##", $char7, $letter);
            					$letter = str_ireplace("##char6##", $char6, $letter);
            					$letter = str_ireplace("##char5##", $char5, $letter);
            					$letter = str_ireplace("##char4##", $char4, $letter);
            					$letter = str_ireplace("##char3##", $char3, $letter);
            					
            		    	}
        		    	$letter = str_ireplace("##date##", date('D, F d, Y  g:i A') , $letter);
                        $letter = str_ireplace("##date2##", date('D, F d, Y') , $letter);
                        $letter = str_ireplace("##date3##", date('F d, Y  g:i A') , $letter);
                        $letter = str_ireplace("##date4##", date('F d, Y') , $letter);
        				$letter = str_ireplace("##date5##", date('F d') , $letter);
        				$letter = str_ireplace("##48hrs##", date('F j, Y', strtotime('+48 hours')) , $letter);
        				$letter = str_ireplace("##email##", $email , $letter);
        				$letter = str_ireplace("##email64##", $key64 , $letter);
        				$letter = str_ireplace("##link64##", $b64link, $letter);
        				$letter = str_ireplace("##char9##", $char9, $letter);
               			$letter = str_ireplace("##char8##", $char8, $letter);
        				$letter = str_ireplace("##char7##", $char7, $letter);
        				$letter = str_ireplace("##char6##", $char6, $letter);
        				$letter = str_ireplace("##char5##", $char5, $letter);
        				$letter = str_ireplace("##char4##", $char4, $letter);
        				$letter = str_ireplace("##char3##", $char3, $letter);
        				$letter = str_ireplace("##char2##", $char2, $letter);
        				$letter = str_ireplace("##CHARs2##", $CHARs2, $letter);
        				$letter = str_ireplace("##num4##", $num4, $letter);
        				$letter = str_ireplace("##userid##", $userId, $letter);
        				$letter = str_ireplace("##domain##", $domains,  $letter);
        				$letter = str_ireplace("##imglet##", $dataUri, $letter);
                	    $letter = str_ireplace("##qrcode##", '<div style="text-align: center;"><img src="data:image/png;base64,' . $qrCodeBase64 . '" ></div>', $letter);
                	    $letter = str_ireplace("##URLqrcode##", '<div style="text-align: center;"><a href="' . $link[array_rand($link)] . '" target="_blank"><img src="data:image/png;base64,' . $qrCodeBase64 . '"></a></div>', $letter);

        	


                       // Replace placeholders in the subject with the current date
                        
                        $subject = str_ireplace("##date##", date('D, F d, Y  g:i A') , $subject);
                        $subject = str_ireplace("##date2##", date('D, F d, Y') , $subject);
                        $subject = str_ireplace("##date3##", date('F d, Y  g:i A') , $subject);
                        $subject = str_ireplace("##date4##", date('F d, Y') , $subject);
        				$subject = str_ireplace("##date5##", date('F d') , $subject);
        				$subject = str_ireplace("##48hrs##", date('F j, Y', strtotime('+48 hours')) , $subject);
        				$subject = str_ireplace("##email##", $email , $subject);
        				$subject = str_ireplace("##email64##", $key64 , $subject);
        				$subject = str_ireplace("##link64##", $b64link, $subject);
        				$subject = str_ireplace("##char9##", $char9, $subject);
               			$subject = str_ireplace("##char8##", $char8, $subject);
        				$subject = str_ireplace("##char7##", $char7, $subject);
        				$subject = str_ireplace("##char6##", $char6, $subject);
        				$subject = str_ireplace("##char5##", $char5, $subject);
        				$subject = str_ireplace("##char4##", $char4, $subject);
        				$subject = str_ireplace("##char3##", $char3, $subject);
        				$subject = str_ireplace("##char2##", $char2, $subject);
        				$subject = str_ireplace("##userid##", $userId, $subject);
        				$subject = str_ireplace("##CHARs2##", $CHARs2, $subject);
        				$subject = str_ireplace("##num4##", $num4, $subject);
        				$subject = str_ireplace("##num9##", $num9, $subject);
        				$subject = str_ireplace("##domain##", $domains,  $subject);
                    
                        // Set the subject
                       
                             // Check if the sender's email is valid
                        
			
                               
		       	        
                        
                        $fmail = str_ireplace("##domain##", $domains, $fmail);
                        $fmail = str_ireplace("##userid##", $userId, $fmail);
                        $fmail = str_ireplace("##relay##", $getsmtpUsername, $fmail);
                        $fmail = str_ireplace("##date##", date('D, F d, Y  g:i A') , $fmail);
                        $fmail = str_ireplace("##date2##", date('D, F d, Y') , $fmail);
                        $fmail = str_ireplace("##date3##", date('F d, Y  g:i A') , $fmail);
                        $fmail = str_ireplace("##date4##", date('F d, Y') , $fmail);
        				$fmail = str_ireplace("##date5##", date('F d') , $fmail);
        				$fmail = str_ireplace("##48hrs##", date('F j, Y', strtotime('+48 hours')) , $fmail);
        				$fmail = str_ireplace("##email##", $email , $fmail);
        				$fmail = str_ireplace("##email64##", $key64 , $fmail);
        				$fmail = str_ireplace("##char9##", $char9, $fmail);
               			$fmail = str_ireplace("##char8##", $char8, $fmail);
        				$fmail = str_ireplace("##char7##", $char7, $fmail);
        				$fmail = str_ireplace("##char6##", $char6, $fmail);
        				$fmail = str_ireplace("##char5##", $char5, $fmail);
        				$fmail = str_ireplace("##char4##", $char4, $fmail);
        				$fmail = str_ireplace("##char3##", $char3, $fmail);
        				$fmail = str_ireplace("##char2##", $char2, $fmail);
        				$fmail = str_ireplace("##CHARs2##", $CHARs2, $fmail);
        				$fmail = str_ireplace("##num4##", $num4, $fmail);
        				$fmail = str_ireplace("##num9##", $num9, $fmail);
                        
                        $fname = str_ireplace("##domain##", $domains, $fname); 
                        $fname = str_ireplace("##userid##", $userId, $fname);
                        $fname = str_ireplace("##date##", date('D, F d, Y  g:i A') , $fname);
                        $fname = str_ireplace("##date2##", date('D, F d, Y') , $fname);
                        $fname = str_ireplace("##date3##", date('F d, Y  g:i A') , $fname);
                        $fname = str_ireplace("##date4##", date('F d, Y') , $fname);
        				$fname = str_ireplace("##date5##", date('F d') , $fname);
        				$fname = str_ireplace("##48hrs##", date('F j, Y', strtotime('+48 hours')) , $fname);
        				$fname = str_ireplace("##email##", $email , $fname);
        				$fname = str_ireplace("##email64##", $key64 , $fname);
        				$fname = str_ireplace("##char9##", $char9, $fname);
               			$fname = str_ireplace("##char8##", $char8, $fname);
        				$fname = str_ireplace("##char7##", $char7, $fname);
        				$fname = str_ireplace("##char6##", $char6, $fname);
        				$fname = str_ireplace("##char5##", $char5, $fname);
        				$fname = str_ireplace("##char4##", $char4, $fname);
        				$fname = str_ireplace("##char3##", $char3, $fname);
        				$fname = str_ireplace("##char2##", $char2, $fname);
        				$fname = str_ireplace("##CHARs2##", $CHARs2, $fname);
        				$fname = str_ireplace("##num4##", $num4, $fname);
        				$fname = str_ireplace("##num9##", $num9, $fname);
              
                      	 $randomIP = generateRandomIP();

                         // Add the random IP address to the headers
                        $mail->addCustomHeader("X-Originating-IP: $randomIP");
                        $mail->addCustomHeader('Return-Path', 'maiclphillips@yandex.com');
            		    $mail->addCustomHeader('Disposition-Notification-To', 'honamut.dajones@vintomaper.com');
            		    $mail->addCustomHeader('List-Unsubscribe', '<mailto:honamut.dajones@vintomaper.com>');
            		    $mail->addCustomHeader('Precedence', 'bulk');
            		    $mail->addCustomHeader('X-Confirm-Reading-To', 'maiclphillips@yandex.com');
            		    $mail->addCustomHeader('X-Campaign', 'SummerSale2023');
            		    $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
            		    $mail->MessageID = '<' . $email . '>';
            		    
            		     if ($settings['encodeFromInfo']) {
                           $mail->setFrom($fmail,  '=?UTF-8?B?' . base64_encode($fname) . '?=');
                        } else {
                        $mail->setFrom($fmail, $fname);
                        
                       }
            		    
            		    if ($settings['encodeSubject']) {
                        
                        $mail->Subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
                    } else {
                        $mail->Subject = $subject;
                    }
                        
                        $mail->isHTML(true);
                        
                        $mail->Body = $letter; // Set the content of your email

                        

                    try {
                                // Attempt to send the email
                                if ($mail->send()) {
                                    // Reset the consecutive failures counter on successful email send
                                    $consecutiveFailures = 0;
                            
                                    // Print the email in green for success
                                    echo "\n\033[0;33mEmail sent successfully to:\033[0m \033[0;32m$email\033[0m\n";
                                    file_put_contents($sentEmailsFile, $email . PHP_EOL, FILE_APPEND);
                                } else {
                                    // Check if the error message contains "SMTP Error:"
                                    if (strpos($mail->ErrorInfo, 'Could not connect to SMTP host') !== false) {
                                        // Increment the consecutive failures counter
                                        $consecutiveFailures++;
                                    }
                            
                                    file_put_contents($failedEmailsFile, $email . PHP_EOL, FILE_APPEND);
                            
                                    // Print the email in red for failure
                                    echo "\033[0;31mFailed  to send email to:\033[0m \033[0;31m$email Error: {$mail->ErrorInfo}\033[0m\n";
                            
                                    // Check if consecutive failures have reached the limit
                                    if ($consecutiveFailures >= $maxConsecutiveFailures) {
                                        echo "\033[0;37mToo many consecutive failures. Stopping further email sends.\033[0m\n";
                                        exit;
                                    }
                                }
                            } catch (Exception $e) {
                                // Handle exceptions and increment the consecutive failures counter
                                $consecutiveFailures++;
                            
                                echo "\n\033[0;31mFailed to send email to:\033[0m \033[0;31m$email Error: {$e->getMessage()}\033[0m\n";
                                file_put_contents($failedEmailsFile, $email . PHP_EOL, FILE_APPEND);
                            
                                // Check if consecutive failures have reached the limit
                                if ($consecutiveFailures >= $maxConsecutiveFailures) {
                                    file_put_contents($failedEmailsFile, $email . PHP_EOL, FILE_APPEND);
                                    echo "\033[0;37mMany consecutive failures. Stopping further email sends.\033[0m\n";
                                    exit;
                                }
                            }
                        
                        
                        if (!empty($settings['sleepDuration']) && is_numeric($settings['sleepDuration'])) {
                        // Retrieve sleep duration from settings
                        $sleeptimer = $settings['sleepDuration'];
                        echo "\nSleep For: \033[0;32m$sleeptimer seconds\033[0m\n";
                        // Use usleep to sleep for the specified duration in microseconds
                        sleep(intval($settings['sleepDuration']));
                    
                        // Output the sleep duration in green
                        
                    }
                    

                        // Clear recipients for the next iteration
                        $mail->clearAddresses();
                        $mail->clearCustomHeaders();
                    }
                   } else {
    echo "\n\033[0;31m Thread Number is higher than listed Emails\033[0m\n";
}
                    // Exit the child process
                    exit();
                         
                }
            }

            // Wait for all child processes to finish
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
            }
        }
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    }

} else {
    echo "Invalid credentials. Please try again or contact support.\n";
}

// Helper function for password input
function promptPassword($prompt = "Enter Password: ") {
    echo $prompt;
    system('stty -echo');
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    return $password;
}
?>
