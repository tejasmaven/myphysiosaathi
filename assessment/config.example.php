<?php
// Copy outside the public web directory, e.g. /var/www/private/myphysiosaathi-assessment.php.
// Point ASSESSMENT_CONFIG_PATH at it in Apache/PHP environment. Never commit real credentials.
return [
    'enabled' => false, // Enable only after configuring and testing delivery.
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_auth' => true,
    'smtp_username' => '',
    'smtp_password' => '', // Gmail App Password, not your normal account password.
    'from_email' => '', // Use the authenticated Gmail address.
    'from_name' => 'My Physio Saathi',
    'admin_email' => '',
    // Directory outside DocumentRoot for small rate-limit counters. No reports are saved here.
    'state_directory' => sys_get_temp_dir() . '/myphysiosaathi-assessment',
];
