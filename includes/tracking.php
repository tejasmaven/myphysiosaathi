<?php
// Shared by all public content pages. Keep customer details out of analytics.
// Install this tag here only, not a second time through a tag manager.
if (($trackingPlacement ?? 'head') === 'head'):
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-4ED7JBKJC6"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-4ED7JBKJC6');
</script>
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "yyl91zg2nj");
</script>
<!-- Shared tracking: HEAD. Google Analytics G-4ED7JBKJC6 and Microsoft Clarity yyl91zg2nj. -->
<?php else: ?>
<!-- Shared tracking: BODY. Reserved for an approved GTM noscript block if needed. -->
<?php endif; ?>
