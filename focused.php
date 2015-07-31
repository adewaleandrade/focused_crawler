<?php
	// include('crawler.php');
	include('FocusedCrawler.php');

	ini_set('memory_limit', '-1');
	$topic = "eventos lazer e turismo em salvador";

	$crawler = new FocusedCrawler($topic);
?>