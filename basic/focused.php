<?php
	// include('crawler.php');
	include('FocusedCrawler.php');

	ini_set('memory_limit', '-1');
	// $topic = "turismo na bahia";
	// $topic = "Sistemas embarcados";
	$topic = "Automação residencial arduino";

	$crawler = new FocusedCrawler($topic);
?>