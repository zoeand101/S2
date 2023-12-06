<?php

require __DIR__.'/vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\Png;
use Endroid\QrCode\QrCode;
use BaconQrCode\Writer;

$factory = (new Factory)
    ->withServiceAccount('list/fb1.json')
    ->withDatabaseUri('https://f1ni-16ac3-default-rtdb.europe-west1.firebasedatabase.app');

$database = $factory->createDatabase();

function addUser($username, $password, $database) {
    $newUserRef = $database->getReference('users')->push();
    $newUserRef->set([
        'username' => $username,
        'password' => $password
    ]);

    return $newUserRef->getKey(); // Return the generated user ID
}

// Usage example
$username = '2';
$password = '2';

$newUserID = addUser($username, $password, $database);

if ($newUserID) {
    echo 'New user added with ID: ' . $newUserID;
} else {
    echo 'Failed to add a new user.';
}
?>
