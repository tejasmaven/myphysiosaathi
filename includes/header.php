<?php
$home=($sitePage??'')==='home';
$anchorPrefix=$home?'':'./';
?>
<header class="site-header"><div class="site-chrome header-inner">
<a class="brand" href="<?=$home?'#opening':'./'?>">My Physio <span class="brand-saathi">Saathi</span><span class="brand-category">Physiotherapy Clinic Software</span></a>
<nav aria-label="Main navigation">
<a href="<?=$anchorPrefix?>#journey">How it works</a>
<a href="<?=$anchorPrefix?>#features">Features</a>
<a href="pricing.php" <?=($sitePage??'')==='pricing'?'aria-current="page"':''?>>Pricing</a>
<a href="<?=$anchorPrefix?>#faq">FAQs</a>
</nav>
<a class="button small" href="<?=$anchorPrefix?>#contact" data-enquiry="Product demo">Book a demo</a>
</div></header>
