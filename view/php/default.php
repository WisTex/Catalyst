<!DOCTYPE html >
<html prefix="og: http://ogp.me/ns#">
<head>
  <title><?php if(x($page,'title')) echo $page['title'] ?></title>
  <script>let baseurl="<?php echo z_root() ?>";</script>
  <?php if(x($page,'htmlhead')) echo $page['htmlhead'] ?>
</head>
<body <?php if($page['direction']) echo 'dir="rtl"' ?> >
	<?php if(x($page,'banner')) echo $page['banner']; ?>
	<header><?php if(x($page,'header')) echo $page['header']; ?></header>
	<nav class="navbar fixed-top navbar-expand-lg navbar-dark bg-dark"><?php if(x($page,'nav')) echo $page['nav']; ?></nav>
	<main>
		<aside id="region_1"><div class="aside_spacer"><div id="left_aside_wrapper"><?php if(x($page,'aside')) echo $page['aside']; ?></div></div></aside>
		<section id="region_2"><?php if(x($page,'content')) echo $page['content']; ?>
					<div id="page-footer"></div>
			<div id="pause"></div>
		</section>
		<aside id="region_3" class="d-none d-xl-table-cell"><div class="aside_spacer"><div id="right_aside_wrapper"><?php if(x($page,'right_aside')) echo $page['right_aside']; ?></div></div></aside>
	</main>
	<footer><?php if(x($page,'footer')) echo $page['footer']; ?></footer>
</body>
</html>
