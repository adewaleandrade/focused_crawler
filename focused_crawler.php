<?php
	include('crawler.php');
	include('FocusedCrawler.php');
	 // phpinfo();die();
	ini_set('memory_limit', '-1');
	$topic = "Realidade Aumentada";
	// echo stem_portuguese("apartamento");die();
	$crawler = new FocusedCrawler($topic);


	// $crawler = new Crawler($topic);

	// debugPrint($crawler->getBaseUrlByTopic());
	// $crawler->initializeWeightTable();
	// $relevantPages  = $crawler->getRelevantPages();
	// debugPrint($relevantPages);
?>