<?php
    require '../src/mail.php';

    $text_body  = 'This is a test email body <br>that will be in text format这里也有中文';
    $html_body  = '<html><head><meta charset="UTF-8"><style type="text/css">body{background:green;}</style></head><body><p>This is a <strong>test email body</strong> <br>that will be in html format这里有中文</p></body></html>';
    $attachments = array(
        '/home/xuwei3/dev/github/utils/src/mail.php'
        );
    $mail_params = array(
        'to'    => 'xuwei3@360.cn',
        'from'  => 'merkerxu@github.com',
        'subject'       => 'test mail标题含中文',
        'text_body'     => $text_body,
        'html_body'     => $html_body,
        'attachments'   => $attachments,
        );
    $sent = mail_svc::send($mail_params);
    if($sent)
        echo 'mail  send successfully';
    else
        echo 'mail  send failed';
