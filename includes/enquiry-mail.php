<?php
require_once dirname(__DIR__).'/assessment/mail.php';
function deliver_assessment_invitation(array $config,array $fields): void {
    $url='https://www.myphysiosaathi.in/free-assesment.php';
    $escape=fn($text)=>htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $name=$escape($fields['full_name']);$clinic=$escape($fields['clinic_name']);
    $mail=assessment_mailer($config);
    $mail->addAddress($fields['email'],$fields['full_name']);
    $mail->addReplyTo($config['admin_email'],'Tejas P Mehta');
    $mail->Subject='Your free clinic workflow assessment | My Physio Saathi';
    $mail->isHTML(true);
    $mail->Body=<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:24px 12px;background:#f1f5f9;color:#374151;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e2e8f0">
<tr><td style="padding:25px 28px;background:#0f172b;border-top:4px solid #e1201b;color:#ffffff"><strong style="font-size:24px">My Physio Saathi</strong><br><span style="font-size:14px;color:#cad5e2">Every patient visit, connected.</span></td></tr>
<tr><td style="padding:28px;line-height:1.6;font-size:16px">
<p style="margin:0 0 16px">Hello {$name},</p>
<h1 style="margin:0 0 18px;font-size:26px;line-height:1.25;color:#0f172b">Your clinic assessment starts here.</h1>
<p>Thank you for requesting a free clinic workflow audit for <strong>{$clinic}</strong>. Start with our self-reported assessment of your clinic's daily routines.</p>
<p>Answer 25 questions about patient records, treatment continuity, payments, staff coordination and information protection. Choose the answer that reflects your current practice. Reliable paper-based processes can score well too.</p>
<table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#e1201b" style="border-radius:6px"><a href="{$url}" style="display:inline-block;padding:14px 22px;color:#ffffff;text-decoration:none;font-weight:bold">Start your free assessment</a></td></tr></table>
<p>You will receive a PDF report based on your answers, with suggested priorities and practical actions. A copy is also sent to the My Physio Saathi team.</p>
<p style="font-size:14px">Please provide clinic contact details only. Do not enter patient names, medical information or identifiable patient records.</p>
<p style="font-size:14px">If the button does not open, use:<br><a href="{$url}" style="color:#b71916;word-break:break-word">{$url}</a></p>
<p style="font-size:13px;color:#45556c">This is a self-reported process assessment, not a clinical, security or compliance certification.</p>
</td></tr><tr><td style="padding:22px 28px;background:#0f172b;color:#ffffff;font-size:14px;line-height:1.6">Tejas P Mehta<br><a href="tel:+919825647083" style="color:#ffffff">Call: +91 98256 47083</a><br><a href="https://www.myphysiosaathi.in/" style="color:#cad5e2">www.myphysiosaathi.in</a><br><span style="color:#cad5e2;font-size:12px">Sent in response to your clinic workflow enquiry.</span></td></tr>
</table></td></tr></table></body></html>
HTML;
    $mail->AltBody="Hello ".$fields['full_name'].",\n\nThank you for requesting a free clinic workflow audit for ".$fields['clinic_name'].".\n\nStart your self-reported 25-question assessment:\n".$url."\n\nReview patient records, treatment continuity, payments, staff coordination and information protection. Reliable paper processes can score well. After submission, your PDF report is emailed to you and the My Physio Saathi team.\n\nDo not enter identifiable patient details or medical information. This is not a clinical, security or compliance certification.\n\nTejas P Mehta | Call: +91 98256 47083\nMy Physio Saathi | Every patient visit, connected.\nSent in response to your clinic workflow enquiry.";
    $mail->send();
}
