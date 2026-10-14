<?php
require_once __DIR__.'/lib/PHPMailer/src/Exception.php';
require_once __DIR__.'/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__.'/lib/PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
function assessment_mailer(array $config): PHPMailer {
    $mail=new PHPMailer(true);
    $mail->isSMTP();$mail->Host=$config['smtp_host'];$mail->Port=(int)$config['smtp_port'];
    $mail->SMTPAuth=$config['smtp_auth'] ?? true;
    $mail->Username=$config['smtp_username'];$mail->Password=$config['smtp_password'];
    $mail->SMTPSecure=$config['smtp_encryption'];$mail->SMTPAutoTLS=true;
    $mail->Timeout=20;$mail->CharSet='UTF-8';$mail->SMTPDebug=0;
    $mail->setFrom($config['from_email'],$config['from_name']);
    return $mail;
}
function deliver_assessment(array $config, array $submission, string $recipient, bool $admin, string $pdf): void {
    $mail=assessment_mailer($config);
    $mail->addAddress($recipient);
    if($admin)$mail->addReplyTo($submission['fields']['email'],$submission['fields']['doctor_name']);
    $mail->Subject=($admin?'New clinic workflow report':'Your clinic workflow report').' | '.$submission['id'];
    if(!$admin)$mail->addReplyTo($config['admin_email'],'Tejas P Mehta');
    $escape=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $reference=$escape($submission['id']);$date=$escape($submission['submitted_at']);
    $doctor=$escape($submission['fields']['doctor_name']);$clinic=$escape($submission['fields']['clinic_name']);
    $heading=$admin?'A new clinic assessment is ready.':'Your clinic workflow report is ready.';
    $greeting=$admin?'Hello My Physio Saathi team,':'Hello '.$doctor.',';
    $intro=$admin?'The assessment for <strong>'.$clinic.'</strong> has been submitted. The self-reported clinic workflow report is attached.':'Thank you for completing the assessment for <strong>'.$clinic.'</strong>. Your self-reported clinic workflow report is attached to this email.';
    $labels=['clinic_name'=>'Clinic name','doctor_name'=>'Doctor full name','location'=>'Location / area','city'=>'City','mobile'=>'Mobile number','email'=>'Email address','beds'=>'Beds / treatment couches'];
    $rows='';$plain='';
    foreach($labels as $key=>$label){
        $value=$submission['fields'][$key]??'';
        $rows.='<tr><th scope="row" align="left" style="padding:10px 12px;background:#eaf0f7;color:#0f172b;font-size:14px;font-weight:bold;border-bottom:1px solid #dce5ef;width:40%;vertical-align:top">'.$label.'</th><td style="padding:10px 12px;font-size:14px;background:#f8fafc;border-bottom:1px solid #dce5ef;word-break:break-word">'.$escape($value).'</td></tr>';
        $plain.=$label.': '.$value."\n";
    }
    $mail->isHTML(true);
    $mail->Body=<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:24px 12px;background:#f1f5f9;color:#374151;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e2e8f0">
<tr><td style="padding:25px 28px;background:#0f172b;border-top:4px solid #e1201b;color:#ffffff"><strong style="font-size:24px">My Physio Saathi</strong><br><span style="font-size:14px;color:#cad5e2">Every patient visit, connected.</span></td></tr>
<tr><td style="padding:28px;line-height:1.6;font-size:16px">
<p style="margin:0 0 16px">{$greeting}</p>
<h1 style="margin:0 0 18px;font-size:26px;line-height:1.25;color:#0f172b">{$heading}</h1>
<p>{$intro}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;background:#edf3f9;border-left:4px solid #e1201b"><tr><td style="padding:16px 18px"><strong style="color:#b71916;font-size:12px;letter-spacing:1px">PDF REPORT ATTACHED</strong><br><strong style="color:#0f172b">Assessment reference: {$reference}</strong><br><span style="font-size:14px">Submitted: {$date}</span></td></tr></table>
<h2 style="font-size:19px;color:#0f172b;margin:22px 0 10px">Start with the priorities.</h2>
<p style="margin:0 0 12px">Open the attached PDF to review:</p>
<ul style="padding-left:22px;margin:0 0 22px"><li>Your domain scores and submitted responses</li><li>Supported priorities and items to confirm</li><li>A suggested action plan and progress measures</li></ul>
<h2 style="font-size:19px;color:#0f172b;margin:22px 0 12px">Clinic details</h2>
<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dce5ef;border-collapse:collapse">{$rows}</table>
<p style="margin-top:24px">If you would like to discuss the findings or see how My Physio Saathi could fit your clinic, contact Tejas. The recommendations remain useful whether you use paper records or software.</p>
<table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#e1201b" style="border-radius:6px"><a href="tel:+919825647083" style="display:inline-block;padding:14px 22px;color:#ffffff;text-decoration:none;font-weight:bold">Call Tejas to discuss your report</a></td></tr></table>
<p style="font-size:13px;color:#45556c;margin-top:24px">This report is based on the clinic's self-reported answers. It identifies process improvement opportunities and is not a clinical, security or compliance certification. Suggested measures and targets are proposals to review, not verified results or clinic commitments.</p>
</td></tr><tr><td style="padding:22px 28px;background:#0f172b;color:#ffffff;font-size:14px;line-height:1.6">Tejas P Mehta<br><a href="tel:+919825647083" style="color:#ffffff">Call: +91 98256 47083</a><br><a href="https://www.myphysiosaathi.in/" style="color:#cad5e2">www.myphysiosaathi.in</a><br><span style="color:#cad5e2;font-size:12px">Sent following your clinic assessment submission.</span></td></tr>
</table></td></tr></table></body></html>
HTML;
    $mail->AltBody=($admin?'A clinic assessment has been submitted.':'Thank you for completing the My Physio Saathi clinic assessment.')."\n\nYour self-reported clinic workflow report is attached. Open the PDF for domain scores, submitted responses, supported priorities, items to confirm and a suggested action plan.\n\nReference: ".$submission['id']."\nSubmitted: ".$submission['submitted_at']."\n\n".$plain."\nThis report is based on the clinic's self-reported answers. It identifies process improvement opportunities and is not a clinical, security or compliance certification. Suggested measures and targets are proposals, not verified results or clinic commitments.\n\nTejas P Mehta | Call: +91 98256 47083\nhttps://www.myphysiosaathi.in/";
    $mail->addStringAttachment($pdf,'My-Physio-Saathi-Report-'.$submission['id'].'.pdf','base64','application/pdf');
    $mail->send();
}
