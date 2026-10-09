<?php
// Included by every public HTML page. Do not place SMTP secrets or customer details here.
// Add Google Analytics OR the GTM container script in the head branch.
// Put GTM's matching noscript iframe in the body branch. Avoid installing the same tag twice.
if(($trackingPlacement??'head')==='head'):
?>
<!-- Shared tracking: HEAD. Paste the approved analytics/container script here. -->
<?php else: ?>
<!-- Shared tracking: BODY. Paste the GTM noscript block here, if used. -->
<?php endif; ?>
