#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';
include_once __DIR__.'/config.inc.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$debug = false;
if(defined('DEBUG_MODE') || (isset($argv[1]) && $argv[1] == '-d'))
    $debug = true;

while(true) {
    try {
        $pdo = new PDO('pgsql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        do {
            $sql = 'SELECT mails.*,
                        users.email
                    FROM mails,
                        users
                    WHERE mails.sent = FALSE
                    AND mails.uid = users.uid
                    LIMIT 50';
 
            $rows = $pdo -> query($sql, PDO::FETCH_ASSOC);
            $rowsCount = 0;
        
            foreach($rows as $row) {
                $rowsCount++;
                try {
                    $phpMailer = new PHPMailer(true);
 
                    if($debug) {
                        $phpMailer -> SMTPDebug = SMTP::DEBUG_SERVER;
                        $phpMailer -> Debugoutput = function($str, $level) {
                            echo "$str\n";
                        };
                    } 
 
                    // SMTP server settings
                    $phpMailer -> IsSMTP();
                    $phpMailer -> Host = MAIL_HOST;
                    $phpMailer -> Port = MAIL_PORT;
                    $phpMailer -> SMTPAutoTLS = true;
                    $phpMailer -> SMTPAuth = MAIL_AUTH;
                    if(MAIL_AUTH) {
                        $phpMailer -> Username = MAIL_USER;
                        $phpMailer -> Password = MAIL_PASS;
                    }
                
                    // Message general
                    $phpMailer -> CharSet = 'UTF-8';
                    $phpMailer -> setFrom(MAIL_FROM, MAIL_FROM_NAME);
                    $phpMailer -> isHTML(true);
                    $phpMailer -> addEmbeddedImage(__DIR__.'/mail-templates/logo.png', 'logo');
                
                    // Recipient address
                    $phpMailer -> addAddress($row['email']);
 
                    // Template
                    $tpl = file_get_contents(__DIR__.'/mail-templates/header.html').
                        file_get_contents(__DIR__.'/mail-templates/'.$row['template'].'.html').
                        file_get_contents(__DIR__.'/mail-templates/footer.html');
                
                    // Data
                    $data = json_decode($row['data'], true);
                    $data['email'] = $row['email'];
                    $data['email_urlencoded'] = urlencode($row['email']);
                    foreach($data as $k => $v) {
                        $tpl = str_replace('{{' . $k . '}}', $v, $tpl);
                    }
                
                    // Extract subject from template
                    $phpMailer -> Subject = '';
                    $subjectTag = strpos($tpl, '[[SUBJECT: ');
                    if($subjectTag !== false) {
                        $subjectEndTag = strpos($tpl, ']]', $subjectTag);
                        if($subjectEndTag !== false) {
                            $subject = substr($tpl, $subjectTag + 11, $subjectEndTag - $subjectTag - 11);
                            $tpl = str_replace("[[SUBJECT: $subject]]", '', $tpl);
                            $phpMailer -> Subject = $subject;
                        }
                    }
                    $phpMailer -> Body = $tpl;  
 
                    // Send
                    $phpMailer -> send();
                
                    // Mark sent
                    $task = array(
                        ':mailid' => $row['mailid'],
                    );
        
                    $sql = 'UPDATE mails SET sent = TRUE WHERE mailid = :mailid';
    
                    $q = $pdo -> prepare($sql);
                    $q -> execute($task);
                }
            
                catch(PHPMailerException $e) {
                    echo 'PHPMailerException: ' . $e -> getMessage() . "\n";
                }
            }
        } while($rowsCount == 50);
    }
    
    catch(PDOException $e) {
        echo 'PDOException: ' . $e -> getMessage() . "\n";
    }
    
    unset($pdo);
    sleep(10);
}

?>
