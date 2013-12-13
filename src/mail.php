<?php
/**
 * A PHP class for sending email message which includes capabilites for sending
 * plaintext, html and multipart messages, including support for attachment
 *
 * @author
 * @version
 */

class mail_svc
{/*{{{*/
    public static function send($send_params)
    {/*{{{*/
        $send_params['subject'] = '=?UTF-8?B?' .
            base64_encode($send_params['subject']) . '?=';
        $boundary_hash  = md5(date('r', time()));
        $header_str     = self::prepare_header($send_params['from'], $boundary_hash);
        $body_str = self::prepare_body($send_params['text_body'], $send_params['html_body'], $send_params['attachments'], $boundary_hash);
        $sent     = mail($send_params['to'], $send_params['subject'], $body_str, $header_str);
        return $sent;
    }/*}}}*/

    private static function prepare_header($from, $boundary_hash)
    {/*{{{*/
        $headers = array();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "From: " . $from;
        $headers[] = "Reply-To: " . $from;
        $headers[] = "Content-Type: multipart/mixed; boundary=\"PHP-mixed-" . $boundary_hash . "\"";
        return implode("\r\n", $headers) . "\r\n"; 
    }/*}}}*/

    private static function prepare_body($plain_msg, $html_msg, $attachments, $boundary_hash)
    {/*{{{*/
        $body_str = '--PHP-mixed-' . $boundary_hash . "\n";
        $body_str .= 'Content-Type: multipart/alternative; boundary="PHP-alt-' .
            $boundary_hash . '"' . "\n\n";
        if(!empty($plain_msg))
        {/*{{{*/
            $body_str .= '--PHP-alt-' . $boundary_hash . "\n";
            $body_str .= 'Content-Type: text/plain; charset="UTF-8"' . "\n";
            $body_str .= 'Content-Transfer-Encoding: 7bit' . "\n\n";
            $body_str .= $plain_msg . "\n\n";
        }/*}}}*/
        if(!empty($html_msg))
        {/*{{{*/
            $body_str .= '--PHP-alt-' . $boundary_hash . "\n";
            $body_str .= 'Content-Type: text/html; charset="UTF-8"' . "\n";
            $body_str .= "Content-Transfer-Encoding: 7bit\n\n";
            $body_str .= $html_msg . "\n\n";
        }/*}}}*/
        $body_str .= '--PHP-alt-' . $boundary_hash . "--\n\n";
        if(!empty($attachments))
        {/*{{{*/
            foreach($attachments as $attach)
            {/*{{{*/
                $filename = basename($attach);

                $body_str .= '--PHP-mixed-' . $boundary_hash . "\n";
                $body_str .= 'Content-Type: application/octet-stream; name="' . $filename . "\"\n";
                $body_str .= 'Content-Transfer-Encoding: base64' . "\n";
                $body_str .= 'Content-Disposition: attachment' . "\n\n";
                $body_str .= chunk_split(base64_encode(file_get_contents($attach)));
                $body_str .= "\n\n";
            }/*}}}*/
        }/*}}}*/

        $body_str .= '--PHP-mixed-' . $boundary_hash . "--\n\n";

        return $body_str;
    }/*}}}*/

}/*}}}*/
