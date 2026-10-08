<?php
require_once __DIR__.'/lib/PHPMailer/src/Exception.php';
require_once __DIR__.'/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__.'/lib/PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
function deliver_assessment(array $config, array $submission, string $recipient, bool $admin, string $pdf): void {
    $mail=new PHPMailer(true);
    $mail->isSMTP();$mail->Host=$config['smtp_host'];$mail->Port=(int)$config['smtp_port'];
    $mail->SMTPAuth=$config['smtp_auth'] ?? true;
    $mail->Username=$config['smtp_username'];$mail->Password=$config['smtp_password'];
    $mail->SMTPSecure=$config['smtp_encryption'];$mail->SMTPAutoTLS=true;
    $mail->Timeout=20;$mail->CharSet='UTF-8';$mail->SMTPDebug=0;
    $mail->setFrom($config['from_email'],$config['from_name']);
    $mail->addAddress($recipient);
    if($admin)$mail->addReplyTo($submission['fields']['email'],$submission['fields']['doctor_name']);
    $mail->Subject=($admin?'New clinic assessment':'Your clinic assessment').' | '.$submission['id'];
    $mail->Body=($admin?'A clinic assessment has been submitted.':'Thank you for completing the My Physio Saathi clinic assessment.')."\n\nThe PDF containing the submitted answers is attached.\n\n";
    foreach($submission['fields'] as $key=>$value)$mail->Body.=$key.': '.$value."\n";
    $mail->Body.="\nReference: ".$submission['id']."\nTejas P Mehta | Call: +91 98256 47083";
    $mail->addStringAttachment($pdf,'My-Physio-Saathi-Assessment-'.$submission['id'].'.pdf','base64','application/pdf');
    $mail->send();
}
