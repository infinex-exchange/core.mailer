<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Sender {
    private $log;
    
    function __construct($log) {
        $this -> log = $log;
        
        $this -> log -> debug('Initialized sender');
    }
    
    public function mail($to, $template, $data) {
        $phpMailer = new PHPMailer(true);

        // SMTP server settings
        $phpMailer -> IsSMTP();
        $phpMailer -> Host = SMTP_HOST;
        $phpMailer -> Port = SMTP_PORT;
        $phpMailer -> SMTPAutoTLS = true;
        $phpMailer -> SMTPAuth = SMTP_AUTH;
        if(SMTP_AUTH) {
            $phpMailer -> Username = SMTP_USER;
            $phpMailer -> Password = SMTP_PASS;
        }
            
        // Message general
        $phpMailer -> CharSet = 'UTF-8';
        $phpMailer -> setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $phpMailer -> isHTML(true);
        $phpMailer -> addEmbeddedImage(__DIR__.'/../mail-templates/logo.png', 'logo');
            
        // Recipient address
        $phpMailer -> addAddress($to);

        // Template
        $tpl = file_get_contents(__DIR__.'/../mail-templates/header.html')
             . file_get_contents(__DIR__.'/../mail-templates/templates/'.$template.'.html')
             . file_get_contents(__DIR__.'/../mail-templates/footer.html');
            
        // Data
        $data['email'] = $to;
        $data['email_urlencoded'] = urlencode($to);
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
    }
}

?>
