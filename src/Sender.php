<?php

use PHPMailer\PHPMailer\PHPMailer;

class Sender {
    private $log;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from;
    private $fromName;
    
    function __construct(
        $log,
        $host,
        $port,
        $user,
        $pass,
        $from,
        $fromName
    ) {
        $this -> log = $log;
        $this -> host = $host;
        $this -> port = $port;
        $this -> user = $user;
        $this -> pass = $pass;
        $this -> from = $from;
        $this -> fromName = $fromName;
        
        $this -> log -> debug('Initialized sender');
    }
    
    public function mail($to, $template, $data) {
        $phpMailer = new PHPMailer(true);

        // SMTP server settings
        $phpMailer -> IsSMTP();
        $phpMailer -> Host = $this -> host;
        $phpMailer -> Port = $this -> port;
        $phpMailer -> SMTPAutoTLS = true;
        $phpMailer -> SMTPAuth = ($this -> user != '' && $this -> pass != '');
        if($phpMailer -> SMTPAuth) {
            $phpMailer -> Username = $this -> user;
            $phpMailer -> Password = $this -> pass;
        }
            
        // Message headers
        $phpMailer -> CharSet = 'UTF-8';
        $phpMailer -> setFrom($this -> from, $this -> fromName);
        $phpMailer -> isHTML(true);
        $phpMailer -> addAddress($to);
        
        // Message rendering
        $phpMailer -> addEmbeddedImage(__DIR__.'/../mail-templates/logo.png', 'logo');
        
        $tpl = file_get_contents(__DIR__.'/../mail-templates/header.html')
             . file_get_contents(__DIR__.'/../mail-templates/templates/'.$template.'.html')
             . file_get_contents(__DIR__.'/../mail-templates/footer.html');
        
        if(!is_array($data))
            $data = [];
        $data['email'] = $to;
        $data['email_urlencoded'] = urlencode($to);
        foreach($data as $k => $v) {
            if(!is_string($v)) {
                $this -> log -> warn("Non-string value of $k in mail $template to $to");
                continue;
            }
            $tpl = str_replace('{{' . $k . '}}', $v, $tpl);
        }
        
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
